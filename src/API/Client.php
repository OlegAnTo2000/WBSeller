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

class Client
{
    public int $responseCode = 0;
    public ?string $responsePhrase = null;
    public array $responseHeaders = [];
    public ?string $rawResponse = null;
    public $response = null;
    public int $rateLimit = 0;
    public int $rateRemaining = 0;
    public int $rateReset = 0;
    public int $rateRetry = 0;
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

    function __construct(string $baseUrl, string $apiKey, ?string $proxyUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;

        $this->stack = new HandlerStack();
        $this->stack->setHandler(new CurlHandler());

        $this->Client = new HttpClient([
            'timeout' => 0, // in seconds
            'verify' => false,
            'handler' => $this->stack,
            'proxy' => $proxyUrl,
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
     * @throws RequestException
     * @throws InvalidArgumentException
     */
    public function request(string $method, string $path, array $params = [], array $addonHeaders = [])
    {
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
                    ]);
                    break;

                case 'POST':
                    $response = $this->Client->post($url, [
                        'headers' => $headers,
                        'body' => json_encode($params)
                    ]);
                    break;

                case 'PUT':
                    $response = $this->Client->put($url, [
                        'headers' => $headers,
                        'body' => json_encode($params)
                    ]);
                    break;

                case 'PATCH':
                    $response = $this->Client->patch($url, [
                        'headers' => $headers,
                        'body' => json_encode($params)
                    ]);
                    break;

                case 'DELETE':
                    $response = $this->Client->delete($url, [
                        'headers' => $headers,
                        'body' => json_encode($params)
                    ]);
                    break;

                case 'MULTIPART':
                    $response = $this->Client->post($url, [
                        'headers' => array_merge([
                            'Authorization' => $this->apiKey,
                            ], $addonHeaders),
                        'multipart' => $params,
                    ]);
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
}
