<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API;

use Dakword\WBSeller\API\Response\ApiResponse;
use Dakword\WBSeller\API\Response\RateLimit;
use Dakword\WBSeller\Exception\ApiClientException;
use Dakword\WBSeller\Exception\ApiResponseDecodingException;
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
 * Каждый вызов `request()` возвращает immutable ApiResponse с телом и метаданными.
 * Для любой HTTP-ошибки клиент вызывает onError и выбрасывает ApiClientException,
 * содержащий тот же ApiResponse. Сетевые и middleware-ошибки преобразуются в
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
    /** @var array<int, callable> */
    private array $onListenerError = [];

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
    public function onListenerError(callable $cb): self { $this->onListenerError[] = $cb; return $this; }

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
     * При успешном запросе возвращается ApiResponse с кодом, заголовками, телом
     * и rate-limit. При HTTP-ошибке этот объект помещается в ApiClientException.
     *
     * Для GET-запросов параметры передаются в query string через Guzzle Query::build.
     * Для остальных методов — сериализуются в JSON-тело.
     *
     * @param string|HttpMethod $method HTTP-метод (GET, POST, PUT, PATCH, DELETE, MULTIPART)
     * @param string $path        Путь относительно baseUrl (например `/api/v3/orders`)
     * @param array  $params      Параметры запроса (query для GET, body для остальных)
     * @param array  $addonHeaders Дополнительные заголовки, мержатся с дефолтными
     * @return ApiResponse Immutable-ответ с телом и HTTP-метаданными
     * @throws ApiClientException При любом HTTP-ответе 4xx/5xx
     * @throws ApiTransportException При сетевой или middleware-ошибке
     * @throws InvalidArgumentException При неподдерживаемом методе
     */
    public function request(
        string|HttpMethod $method,
        string $path,
        array $params = [],
        array $addonHeaders = []
    ): ApiResponse {
        $method = $method instanceof HttpMethod ? $method->value : strtoupper($method);
        $httpMethod = HttpMethod::tryFrom($method);
        if ($httpMethod === null) {
            throw new InvalidArgumentException('Unsupported request method: ' . $method);
        }

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

        $this->emit('request', $this->onRequest, [
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
                $apiResponse = $this->createResponse($resp);
                $message = $this->errorMessage($apiResponse);

                $this->emit('error', $this->onError, [
                    'request_id'      => $requestId,
                    'method'          => $method,
                    'url'             => $url,
                    'status'          => $apiResponse->statusCode,
                    'raw'             => $apiResponse->text(),
                    'duration_ms'     => (int) ((microtime(true) - $startedAt) * 1000),
                    'exception_class' => get_class($exc),
                    'message'         => $message,
                ]);

                throw new ApiClientException(
                    $message,
                    $apiResponse->statusCode,
                    $exc,
                    $apiResponse,
                );
            }

            $this->emitTransportError($requestId, $method, $url, $startedAt, $exc);
            throw new ApiTransportException($exc->getMessage(), 0, $exc);
        } catch (Throwable $exc) {
            $this->emitTransportError($requestId, $method, $url, $startedAt, $exc);
            throw new ApiTransportException($exc->getMessage(), 0, $exc);
        }

        $apiResponse = $this->createResponse($response);

        $durationMs = (int)((microtime(true) - $startedAt) * 1000);
        $this->emit('response', $this->onResponse, [
            'request_id'  => $requestId,
            'method'      => $method,
            'url'         => $url,
            'status'      => $apiResponse->statusCode,
            'headers'     => $this->maskHeaders($apiResponse->headers),
            'raw'         => $apiResponse->text(),
            'duration_ms' => $durationMs,
        ]);

        return $apiResponse;
    }

    private function createResponse(ResponseInterface $response): ApiResponse
    {
        $rawBody = (string) $response->getBody();

        return new ApiResponse(
            body: $rawBody,
            statusCode: $response->getStatusCode(),
            reasonPhrase: $response->getReasonPhrase(),
            headers: $response->getHeaders(),
            rateLimit: new RateLimit(
                limit: (int) $response->getHeaderLine('X-Ratelimit-Limit'),
                remaining: (int) $response->getHeaderLine('X-Ratelimit-Remaining'),
                reset: (int) $response->getHeaderLine('X-Ratelimit-Reset'),
                retry: (int) $response->getHeaderLine('X-Ratelimit-Retry'),
            ),
        );
    }

    private function errorMessage(ApiResponse $response): string
    {
        try {
            $body = $response->json();
        } catch (ApiResponseDecodingException) {
            $body = null;
        }

        if (is_object($body) && isset($body->errors[0]) && is_string($body->errors[0])) {
            return $body->errors[0];
        }
        if (is_object($body) && isset($body->errorText) && is_string($body->errorText)) {
            return $body->errorText;
        }
        if (is_object($body) && isset($body->message) && is_string($body->message) && $body->message !== '') {
            return $body->message;
        }
        if (!$response->isEmpty()) {
            return $response->text();
        }

        return $response->reasonPhrase ?: 'HTTP request failed';
    }

    private function emitTransportError(
        string $requestId,
        string $method,
        string $url,
        float $startedAt,
        Throwable $exception,
    ): void {
        $this->emit('error', $this->onError, [
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
     * Вызывает всех слушателей события и передаёт их исключения в listener_error.
     *
     * Ошибки внутри callback не должны прерывать основной запрос,
     * поэтому все Throwable перехватываются и отправляются отдельному обработчику.
     */
    private function emit(string $eventName, array $listeners, array $event): void
    {
        foreach ($listeners as $index => $cb) {
            try {
                $cb($event);
            } catch (Throwable $exception) {
                $this->reportListenerError($eventName, $index, $exception);
            }
        }
    }

    private function reportListenerError(string $eventName, int $index, Throwable $exception): void
    {
        $event = [
            'event' => $eventName,
            'listener_index' => $index,
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ];

        if ($this->onListenerError === []) {
            error_log(sprintf('WBSeller listener "%s" #%d failed: %s', $eventName, $index, $exception->getMessage()));
            return;
        }

        foreach ($this->onListenerError as $listener) {
            try {
                $listener($event);
            } catch (Throwable $reportingException) {
                error_log('WBSeller listener error handler failed: ' . $reportingException->getMessage());
            }
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
