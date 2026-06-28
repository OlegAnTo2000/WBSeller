<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API;

use Dakword\WBSeller\Exception\ApiClientException;
use Dakword\WBSeller\Exception\ApiTransportException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Query;
use Dakword\WBSeller\Enum\HttpMethod;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * HTTP-клиент для запросов к WildBerries API.
 *
 * Обёртка над GuzzleHttp с поддержкой:
 *   - авторизации через заголовок `Authorization`
 *   - прокси (динамически переопределяемый на уровне запроса)
 *   - middleware-стека GuzzleHttp (HandlerStack)
 *   - событий onRequest / onResponse / onError для логирования
 *   - маскировки чувствительных заголовков в событиях
 *   - чтения rate-limit заголовков WB (`X-Ratelimit-*`)
 *
 * После каждого вызова `request()` публичные свойства содержат метаданные ответа.
 * Для любой HTTP-ошибки клиент заполняет свойства, вызывает onError и выбрасывает
 * ApiClientException. Сетевые и middleware-ошибки преобразуются в
 * ApiTransportException. Исходное исключение доступно через getPrevious().
 *
 * Таймаут: 60 сек. на ответ, 15 сек. на соединение. SSL-верификация настраивается
 * через конструктор и по умолчанию включена.
 * Сам Client не выполняет retry: эта логика находится в AbstractEndpoint и
 * по умолчанию выключена.
 *
 * Подводный камень: экземпляр HttpClient создаётся один раз в конструкторе,
 * но прокси передаётся в каждый запрос отдельно — поэтому смена `$proxyUrl`
 * через `setProxyUrl()` работает немедленно для следующего запроса.
 */
class Client
{
    /** HTTP-статус последнего ответа (например 200, 429, 401). Сбрасывается в 0 перед каждым запросом. */
    public int $responseCode       = 0;
    /** Текстовая фраза HTTP-статуса (например 'OK', 'Too Many Requests'). */
    public ?string $responsePhrase = null;
    /** Заголовки ответа в виде массива ['Header-Name' => ['value']]. */
    public array $responseHeaders  = [];
    /** Сырое тело ответа в виде строки (null до первого запроса). */
    public ?string $rawResponse    = null;
    /** JSON с естественным PHP-типом, null для пустого тела, строка для невалидного JSON. */
    public $response               = null;

    /** Лимит запросов в окне (X-Ratelimit-Limit). 0 если заголовок отсутствует. */
    public int $rateLimit          = 0;
    /** Оставшееся количество запросов в текущем окне (X-Ratelimit-Remaining). */
    public int $rateRemaining      = 0;
    /** Unix-timestamp сброса счётчика rate limit (X-Ratelimit-Reset). */
    public int $rateReset          = 0;
    /** Рекомендуемое время ожидания в секундах при превышении лимита (X-Ratelimit-Retry). */
    public int $rateRetry          = 0;

    /** Текущий прокси URL. Можно менять между запросами через setProxyUrl(). */
    public ?string $proxyUrl       = null;
    
    private string $baseUrl;
    private string $apiKey;
    private HttpClient $Client;
    private HandlerStack $stack;
    /** @var array<int, callable> */
    private array $onRequest = [];
    /** @var array<int, callable> */
    private array $onResponse = [];
    /** @var array<int, callable> */
    private array $onError = [];

    function __construct(
        string $baseUrl,
        string $apiKey,
        ?string $proxyUrl = null,
        bool $verifySsl = true,
        ?HandlerStack $stack = null
    ) {
        $this->baseUrl  = rtrim($baseUrl, '/');
        $this->apiKey   = $apiKey;
        $this->proxyUrl = $proxyUrl;
        $this->stack = $stack ?? new HandlerStack(new CurlHandler());

        $this->Client = new HttpClient([
            'timeout'         => 60,
            'connect_timeout' => 15,
            'verify'          => $verifySsl,
            'handler'         => $this->stack,
            'proxy'           => $this->proxyUrl,
        ]);
    }

    public function onRequest(callable $cb): self { $this->onRequest[] = $cb; return $this; }
    public function onResponse(callable $cb): self { $this->onResponse[] = $cb; return $this; }
    public function onError(callable $cb): self { $this->onError[] = $cb; return $this; }

    public function addMiddleware(callable $middleware, string $name = ''): void
    {
        $this->stack->push($middleware, $name);
    }

    /**
     * Выполняет HTTP-запрос к WB API.
     *
     * Поддерживаемые методы: GET, POST, PUT, PATCH, DELETE, MULTIPART.
     * MULTIPART отправляется как POST с `multipart/form-data` (без JSON-обёртки).
     *
     * Перед запросом сбрасываются все публичные поля ответа.
     * После запроса — заполняются кодом, заголовками, телом и rate-limit данными.
     *
     * При HTTP-ошибке свойства заполняются из ответа, после чего выбрасывается
     * ApiClientException независимо от формата тела.
     *
     * Для GET-запросов параметры передаются в query string через Guzzle Query::build.
     * Для остальных методов — сериализуются в JSON-тело.
     *
     * @param string|HttpMethod $method HTTP-метод (GET, POST, PUT, PATCH, DELETE, MULTIPART)
     * @param string $path        Путь относительно baseUrl (например `/api/v3/orders`)
     * @param array  $params      Параметры запроса (query для GET, body для остальных)
     * @param array  $addonHeaders Дополнительные заголовки, мержатся с дефолтными
     * @return mixed JSON с естественным PHP-типом, null для пустого тела или строка для невалидного JSON
     * @throws ApiClientException При любом HTTP-ответе 4xx/5xx
     * @throws ApiTransportException При сетевой или middleware-ошибке
     * @throws InvalidArgumentException При неподдерживаемом методе
     */
    public function request(
        string|HttpMethod $method,
        string $path,
        array $params = [],
        array $addonHeaders = []
    ) {
        $method = $method instanceof HttpMethod ? $method->value : strtoupper($method);
        $httpMethod = HttpMethod::tryFrom($method);
        if ($httpMethod === null) {
            throw new InvalidArgumentException('Unsupported request method: ' . $method);
        }

        $this->responseCode    = 0;
        $this->responsePhrase  = null;
        $this->responseHeaders = [];
        $this->rawResponse     = null;
        $this->response        = null;
        $this->rateLimit       = 0;
        $this->rateRemaining   = 0;
        $this->rateReset       = 0;
        $this->rateRetry       = 0;

        $startedAt = microtime(true);
        $requestId = bin2hex(random_bytes(8)); // request_id для корреляции запросов

        $defaultHeaders = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => $this->apiKey,
        ];
        $headers = array_merge($defaultHeaders, $addonHeaders);
        $url = $this->baseUrl . $path;

        // Добавляем прокси в каждую отправку запроса, чтобы он учитывался даже если `$this->proxyUrl` меняется динамически.
        $proxyOption = is_string($this->proxyUrl) && $this->proxyUrl !== '' ? ['proxy' => $this->proxyUrl] : [];

        $this->emit($this->onRequest, [
            'id'      => $requestId,
            'method'  => $method,
            'url'     => $url,
            'headers' => $this->maskHeaders($headers),   // лучше заранее замаскировать Authorization
            'params'  => $params,                        // или null если не хочешь логировать
        ]);

        try {
            switch ($httpMethod) {
                case HttpMethod::GET:
                    $response = $this->Client->get($url, [
                        'headers' => $headers,
                        'query' => Query::build($params),
                    ] + $proxyOption);
                    break;

                case HttpMethod::POST:
                    $response = $this->Client->post($url, [
                        'headers' => $headers,
                        'body' => json_encode($params)
                    ] + $proxyOption);
                    break;

                case HttpMethod::PUT:
                    $response = $this->Client->put($url, [
                        'headers' => $headers,
                        'body' => json_encode($params)
                    ] + $proxyOption);
                    break;

                case HttpMethod::PATCH:
                    $response = $this->Client->patch($url, [
                        'headers' => $headers,
                        'body' => json_encode($params)
                    ] + $proxyOption);
                    break;

                case HttpMethod::DELETE:
                    $response = $this->Client->delete($url, [
                        'headers' => $headers,
                        'body' => json_encode($params)
                    ] + $proxyOption);
                    break;

                case HttpMethod::MULTIPART:
                    $response = $this->Client->post($url, [
                        'headers' => array_merge([
                            'Authorization' => $this->apiKey,
                            ], $addonHeaders),
                        'multipart' => $params,
                    ] + $proxyOption);
                    break;

            }
        } catch (RequestException $exc) {
            if ($exc->hasResponse()) {
                $resp = $exc->getResponse();
                $this->captureResponse($resp);
                $message = $this->errorMessage($this->response, $this->responsePhrase);

                $this->emit($this->onError, [
                    'request_id'      => $requestId,
                    'method'          => $method,
                    'url'             => $url,
                    'status'          => $this->responseCode,
                    'raw'             => $this->rawResponse,
                    'duration_ms'     => (int) ((microtime(true) - $startedAt) * 1000),
                    'exception_class' => get_class($exc),
                    'message'         => $message,
                ]);

                throw new ApiClientException(
                    $message,
                    $this->responseCode,
                    $exc,
                    $this->response,
                    $this->rawResponse,
                    $this->responseHeaders,
                );
            }

            $this->emitTransportError($requestId, $method, $url, $startedAt, $exc);
            throw new ApiTransportException($exc->getMessage(), 0, $exc);
        } catch (Throwable $exc) {
            $this->emitTransportError($requestId, $method, $url, $startedAt, $exc);
            throw new ApiTransportException($exc->getMessage(), 0, $exc);
        }

        $this->captureResponse($response);

        $durationMs = (int)((microtime(true) - $startedAt) * 1000);
        $this->emit($this->onResponse, [
            'request_id'  => $requestId,
            'method'      => $method,
            'url'         => $url,
            'status'      => $this->responseCode,
            'headers'     => $this->maskHeaders($this->responseHeaders),
            'raw'         => $this->rawResponse,
            'duration_ms' => $durationMs,
        ]);

        return $this->response;
    }

    private function captureResponse(ResponseInterface $response): void
    {
        $this->responseCode = $response->getStatusCode();
        $this->responsePhrase = $response->getReasonPhrase();
        $this->responseHeaders = $response->getHeaders();
        $this->rateLimit = (int) $response->getHeaderLine('X-Ratelimit-Limit');
        $this->rateRemaining = (int) $response->getHeaderLine('X-Ratelimit-Remaining');
        $this->rateReset = (int) $response->getHeaderLine('X-Ratelimit-Reset');
        $this->rateRetry = (int) $response->getHeaderLine('X-Ratelimit-Retry');
        $this->rawResponse = (string) $response->getBody();
        $this->response = $this->decodeResponse($this->rawResponse);
    }

    private function errorMessage(mixed $body, ?string $fallback): string
    {
        if (is_object($body) && isset($body->errors[0]) && is_string($body->errors[0])) {
            return $body->errors[0];
        }
        if (is_object($body) && isset($body->errorText) && is_string($body->errorText)) {
            return $body->errorText;
        }
        if (is_object($body) && isset($body->message) && is_string($body->message) && $body->message !== '') {
            return $body->message;
        }
        if (is_string($body) && $body !== '') {
            return $body;
        }

        return $fallback ?: 'HTTP request failed';
    }

    private function emitTransportError(
        string $requestId,
        string $method,
        string $url,
        float $startedAt,
        Throwable $exception,
    ): void {
        $this->emit($this->onError, [
            'request_id' => $requestId,
            'method' => $method,
            'url' => $url,
            'status' => null,
            'raw' => null,
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);
    }

    /**
     * JSON сохраняет естественный тип, пустое тело становится null,
     * невалидный JSON возвращается исходной строкой.
     */
    private function decodeResponse(string $body): mixed
    {
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }

    /**
     * Вызывает всех слушателей события, подавляя их исключения.
     *
     * Ошибки внутри callback не должны прерывать основной запрос,
     * поэтому все Throwable перехватываются и молча игнорируются.
     */
    private function emit(array $listeners, array $event): void
    {
        foreach ($listeners as $cb) {
            try { $cb($event); } catch (\Throwable $e) { /* не ломаем запрос */ }
        }
    }

    /**
     * Маскирует чувствительные заголовки (Authorization и т.п.)
     */
    private function maskHeaders(array $headers): array
    {
        static $sensitive = [
            'authorization',
            'proxy-authorization',
            'x-api-key',
            'api-key',
        ];

        $masked = [];

        foreach ($headers as $name => $values) {
            if (in_array(strtolower($name), $sensitive, true)) {
                $masked[$name] = ['***masked***'];
            } else {
                $masked[$name] = $values;
            }
        }

        return $masked;
    }

    /**
     * Установить прокси URL, будет использоваться в каждом запросе
     * и Прокси в запросе должен "перебивать" прокси из конструктора HTTPClient
     *
     * @param string|null $proxyUrl
     * @return self
     */
    public function setProxyUrl(?string $proxyUrl): self
    {
        $this->proxyUrl = $proxyUrl;
        return $this;
    }
}
