<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Query;
use InvalidArgumentException;

/**
 * HTTP-клиент для запросов к WildBerries API.
 *
 * Обёртка над GuzzleHttp с поддержкой:
 *   - авторизации через заголовок `Authorization`
 *   - прокси (динамически переопределяемый на уровне запроса)
 *   - middleware-стека GuzzleHttp (HandlerStack)
 *   - событий onRequest / onResponse / onError для логирования
 *   - маскировки чувствительных заголовков в событиях
 *   - чтения rate-limit заголовков WB (`X-RateLimit-*`)
 *
 * После каждого вызова `request()` публичные свойства содержат метаданные ответа.
 * При HTTP-ошибке (4xx/5xx) Guzzle бросает исключение, но свойства (`responseCode`,
 * `response`, `rawResponse`) всё равно заполняются из тела ошибочного ответа.
 *
 * Таймаут: 60 сек. на ответ, 15 сек. на соединение. SSL-верификация отключена.
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
    /** Декодированное тело ответа: object если ответ был JSON, string иначе. */
    public $response               = null;

    /** Лимит запросов в окне (X-RateLimit-Limit). 0 если заголовок отсутствует. */
    public int $rateLimit          = 0;
    /** Оставшееся количество запросов в текущем окне (X-RateLimit-Remaining). */
    public int $rateRemaining      = 0;
    /** Unix-timestamp сброса счётчика rate limit (X-RateLimit-Reset). */
    public int $rateReset          = 0;
    /** Рекомендуемое время ожидания в секундах при превышении лимита (X-RateLimit-Retry). */
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
        bool $verifySsl = true
    ) {
        $this->baseUrl  = rtrim($baseUrl, '/');
        $this->apiKey   = $apiKey;
        $this->proxyUrl = $proxyUrl;
        $this->stack    = new HandlerStack();
        $this->stack->setHandler(new CurlHandler());

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
     * При HTTP-ошибке: свойства заполняются из ответа ошибки, затем
     * пробрасывается оригинальное Guzzle-исключение (не подавляется).
     * Если тело ошибочного ответа — валидный JSON, он доступен через `$this->response`.
     *
     * Для GET-запросов параметры передаются в query string через Guzzle Query::build.
     * Для остальных методов — сериализуются в JSON-тело.
     *
     * @param string $method      HTTP-метод (GET, POST, PUT, PATCH, DELETE, MULTIPART)
     * @param string $path        Путь относительно baseUrl (например `/api/v3/orders`)
     * @param array  $params      Параметры запроса (query для GET, body для остальных)
     * @param array  $addonHeaders Дополнительные заголовки, мержатся с дефолтными
     * @return mixed Декодированный JSON (object/array) или строка при не-JSON ответе
     * @throws RequestException При сетевой ошибке или HTTP-ошибочном ответе
     * @throws InvalidArgumentException При неподдерживаемом методе
     */
    public function request(
        string $method,
        string $path,
        array $params = [],
        array $addonHeaders = []
    ) {
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
            'method'  => strtoupper($method),
            'url'     => $url,
            'headers' => $this->maskHeaders($headers),   // лучше заранее замаскировать Authorization
            'params'  => $params,                        // или null если не хочешь логировать
        ]);

        try {
            switch (strtoupper($method)) {
                case 'GET':
                    $response = $this->Client->get($url, [
                        'headers' => $headers,
                        'query' => Query::build($params),
                    ] + $proxyOption);
                    break;

                case 'POST':
                    $response = $this->Client->post($url, [
                        'headers' => $headers,
                        'body' => json_encode($params)
                    ] + $proxyOption);
                    break;

                case 'PUT':
                    $response = $this->Client->put($url, [
                        'headers' => $headers,
                        'body' => json_encode($params)
                    ] + $proxyOption);
                    break;

                case 'PATCH':
                    $response = $this->Client->patch($url, [
                        'headers' => $headers,
                        'body' => json_encode($params)
                    ] + $proxyOption);
                    break;

                case 'DELETE':
                    $response = $this->Client->delete($url, [
                        'headers' => $headers,
                        'body' => json_encode($params)
                    ] + $proxyOption);
                    break;

                case 'MULTIPART':
                    $response = $this->Client->post($url, [
                        'headers' => array_merge([
                            'Authorization' => $this->apiKey,
                            ], $addonHeaders),
                        'multipart' => $params,
                    ] + $proxyOption);
                    break;

                default:
                    throw new InvalidArgumentException('Unsupported request method: ' . strtoupper($method));
            }
        } catch (RequestException | ClientException $exc) {
            if ($exc->hasResponse()) {
                $resp = $exc->getResponse();
        
                $this->responseCode    = $resp->getStatusCode();
                $this->responsePhrase  = $resp->getReasonPhrase();
                $this->responseHeaders = $resp->getHeaders();
        
                $this->rateLimit     = (int) $resp->getHeaderLine('X-RateLimit-Limit') ?: 0;
                $this->rateRemaining = (int) $resp->getHeaderLine('X-RateLimit-Remaining') ?: 0;
                $this->rateReset     = (int) $resp->getHeaderLine('X-RateLimit-Reset') ?: 0;
                $this->rateRetry     = (int) $resp->getHeaderLine('X-RateLimit-Retry') ?: 0;
        
                $body = (string) $resp->getBody();
                $this->rawResponse = $body;
        
                $jsonDecoded = json_decode($body);
                if (!json_last_error()) {
                    $this->response = $jsonDecoded;
        
                    $durationMs = (int)((microtime(true) - $startedAt) * 1000);
                    $this->emit($this->onError, [
                        'request_id'      => $requestId,
                        'method'          => strtoupper($method),
                        'url'             => $url,
                        'status'          => $this->responseCode,
                        'raw'             => $this->rawResponse,
                        'duration_ms'     => $durationMs,
                        'exception_class' => get_class($exc),
                        'message'         => $exc->getMessage(),
                    ]);

                    return $jsonDecoded;
                }

                // exception без response (timeout, DNS и т.п.)
                $durationMs = (int)((microtime(true) - $startedAt) * 1000);
                $this->emit($this->onError, [
                    'request_id'      => $requestId,
                    'method'          => strtoupper($method),
                    'url'             => $url,
                    'status'          => null,
                    'raw'             => null,
                    'duration_ms'     => $durationMs,
                    'exception_class' => get_class($exc),
                    'message'         => $exc->getMessage(),
                ]);
        
                // если тело не JSON — оставим строкой в response (удобно для логов)
                $this->response = $body;
            }
        
            throw $exc;
        }

        $this->responseCode    = $response->getStatusCode();
        $this->responsePhrase  = $response->getReasonPhrase();
        $this->responseHeaders = $response->getHeaders();

        $this->rateLimit     = (int) $response->getHeaderLine('X-RateLimit-Limit') ?: 0;
        $this->rateRemaining = (int) $response->getHeaderLine('X-RateLimit-Remaining') ?: 0;
        $this->rateReset     = (int) $response->getHeaderLine('X-RateLimit-Reset') ?: 0;
        $this->rateRetry     = (int) $response->getHeaderLine('X-RateLimit-Retry') ?: 0;

        $responseContent = (string) $response->getBody();
        $this->rawResponse = $responseContent;

        $jsonDecoded = json_decode($responseContent);

        $this->response = (json_last_error() ? $responseContent : $jsonDecoded);

        $durationMs = (int)((microtime(true) - $startedAt) * 1000);
        $this->emit($this->onResponse, [
            'request_id'  => $requestId,
            'method'      => strtoupper($method),
            'url'         => $url,
            'status'      => $this->responseCode,
            'headers'     => $this->maskHeaders($this->responseHeaders),
            'raw'         => $this->rawResponse,
            'duration_ms' => $durationMs,
        ]);

        return $this->response;
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
