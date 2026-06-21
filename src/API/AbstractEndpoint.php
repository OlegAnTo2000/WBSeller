<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API;

use Dakword\WBSeller\API\Client;
use Dakword\WBSeller\APIToken;
use Dakword\WBSeller\Exception\ApiClientException;
use Dakword\WBSeller\Exception\ApiTimeRestrictionsException;
use Dakword\WBSeller\Exception\WBSellerException;
use InvalidArgumentException;

/**
 * Базовый класс для всех endpoint WB API.
 *
 * Отвечает за:
 *   1. Создание и настройку HTTP-клиента (Client) при инстанцировании.
 *   2. Валидацию токена: срок действия и права доступа к конкретному endpoint.
 *   3. Регистрацию middlewares и listeners из конфигурации API-фасада.
 *   4. Маршрутизацию HTTP-запросов (getRequest, postRequest и т.д.) через Client.
 *   5. Логику retry: автоматический повтор на 429 и 504 с задержкой.
 *   6. Обработку стандартных ошибок WB: 400 (временные ограничения), 401, 429, 504.
 *   7. Предоставление методов для чтения метаданных последнего ответа.
 *
 * Подводные камни:
 *   - Каждый экземпляр endpoint — это отдельный HTTP-клиент со своим state.
 *     Не переиспользуйте один экземпляр из разных потоков.
 *   - По умолчанию выполняется 3 попытки с задержкой 5 сек при 429/504.
 *     Переопределяется через retryOnTooManyRequests().
 *   - Ответы WB при 429 могут содержать "Технический перерыв до HH:MM" —
 *     в этом случае retry не выполняется, сразу бросается ApiTimeRestrictionsException.
 *   - Метод `middleware()` в дочернем классе (если определён) автоматически
 *     добавляется в Guzzle HandlerStack при инициализации.
 *   - Валидация токена срабатывает только если ключ имеет JWT-формат (3 части через точку).
 *     Нестандартные ключи пропускаются без ошибки.
 */
abstract class AbstractEndpoint
{
    /**
     * Имя секции API для валидации прав токена.
     *
     * Переопределяется в каждом дочернем endpoint-классе.
     * Должно совпадать с ключами APIToken::$apiFlagPosition ('adv', 'content', и т.д.).
     * Пустая строка — валидация прав пропускается (для тестового endpoint или subpoints).
     */
    protected string $apiName = '';

    private string $locale = 'ru';
    private int $attempts = 3;
    private int $retryDelay = 5_000;
    private Client $Client;
    private ?string $proxyUrl = null;

    /**
     * Инициализирует endpoint: валидирует токен, создаёт HTTP-клиент,
     * подключает listeners и middlewares.
     *
     * Валидация токена:
     *   - Если ключ не является JWT (нет трёх частей через точку) — пропускается.
     *   - Если токен истёк — бросает ApiClientException(401) до первого запроса.
     *   - Если токен не имеет прав на $apiName — бросает ApiClientException(403).
     *
     * Порядок подключения middlewares важен для Guzzle HandlerStack (LIFO):
     *   1. Встроенный `middleware()` дочернего класса (если определён).
     *   2. Пользовательские middlewares из конфигурации (в порядке добавления).
     *
     * @param string      $baseUrl     Базовый URL API (без trailing slash)
     * @param string      $key         API-ключ авторизации
     * @param string|null $proxy       Прокси URL (null — без прокси)
     * @param string|null $locale      Язык ответов ('ru', 'en', …)
     * @param array       $middlewares Guzzle middlewares ['name' => callable] или [callable]
     * @param array       $listeners   Обработчики событий ['request' => [], 'response' => [], 'error' => []]
     * @param bool        $verifySsl   Проверять SSL-сертификат сервера (по умолчанию true)
     * @throws ApiClientException Если токен истёк или не имеет прав на этот endpoint
     */
    public function __construct(
        string $baseUrl,
        string $key,
        ?string $proxy       = null,
        ?string $locale      = null,
        array   $middlewares = [],
        array   $listeners   = [],
        bool    $verifySsl   = true
    ) {
        $this->validateToken($key);

        $this->locale   = $locale ?? 'ru';
        $this->proxyUrl = $proxy;
        $this->Client   = new Client(rtrim($baseUrl, '/'), $key, $this->proxyUrl, $verifySsl);

        foreach (($listeners['request'] ?? []) as $cb) {
            $this->Client->onRequest($cb);
        }
        foreach (($listeners['response'] ?? []) as $cb) {
            $this->Client->onResponse($cb);
        }
        foreach (($listeners['error'] ?? []) as $cb) {
            $this->Client->onError($cb);
        }

        if (method_exists($this, 'middleware')) {
            $this->Client->addMiddleware($this->middleware());
        }
        foreach ($middlewares as $name => $middleware) {
            if (is_callable($middleware)) {
                $middlewareName = is_string($name) ? $name : '';
                $this->Client->addMiddleware($middleware, $middlewareName);
            }
        }
    }

    /**
     * Магический вызов — открывает доступ к protected HTTP-методам извне.
     *
     * Subpoint-классы (AdvAuto, Tags и т.д.) вызывают методы родительского endpoint
     * через `$this->endpoint->getRequest(...)`. Поскольку эти методы protected,
     * их нельзя вызвать напрямую — __call перехватывает вызов и делегирует его.
     *
     * Разрешены только: getRequest, postRequest, putRequest, patchRequest,
     * deleteRequest, multipartRequest. Любые другие имена бросают InvalidArgumentException.
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this, $method)
            && in_array($method, ['getRequest', 'postRequest', 'putRequest', 'patchRequest', 'deleteRequest', 'multipartRequest'])
        ) {
            return call_user_func_array([$this, $method], $parameters);
        }
        throw new InvalidArgumentException('Magic request methods not exists');
    }

    /**
     * Установить прокси URL, заменяет его также в Client
     *
     * @param string|null $proxyUrl
     * @return self
     */
    public function setProxyUrl(?string $proxyUrl): self
    {
        $this->proxyUrl = $proxyUrl;
        $this->Client->setProxyUrl($proxyUrl);
        return $this;
    }

    /**
     * Получить прокси URL
     *
     * @return string|null
     */
    public function getProxyUrl(): ?string
    {
        return $this->proxyUrl;
    }

    /**
     * Проверка подключения к WB API
     *
     * @link https://openapi.wildberries.ru/general/ping/ru/#/paths/~1ping/get
     *
     * @return object {TS: string, status: "OK"}
     */
    public function ping(): object
    {
        return $this->getRequest('/ping');
    }

    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * Переопределить параметры retry для этого экземпляра endpoint.
     *
     * По умолчанию: 3 попытки с задержкой 5 000 мс (5 секунд).
     * Retry срабатывает при ответах 429 (не "технический перерыв") и 504.
     *
     * @param int $attempts Максимальное суммарное число попыток (не дополнительных retry)
     * @param int $delay    Задержка в миллисекундах между попытками
     * @return self
     */
    public function retryOnTooManyRequests(int $attempts = 5, int $delay = 5_000): self
    {
        $this->attempts   = $attempts;
        $this->retryDelay = $delay;

        return $this;
    }

    /** HTTP-статус последнего запроса (0 до первого запроса). */
    public function responseCode(): int
    {
        return $this->Client->responseCode;
    }

    /** Текстовая фраза HTTP-статуса последнего запроса. */
    public function responsePhrase(): ?string
    {
        return $this->Client->responsePhrase;
    }

    /** Заголовки последнего ответа в виде ['Header-Name' => ['value']]. */
    public function responseHeaders(): array
    {
        return $this->Client->responseHeaders;
    }

    /** Сырое тело последнего ответа в виде строки. */
    public function rawResponse(): ?string
    {
        return $this->Client->rawResponse;
    }

    /** Декодированный ответ последнего запроса (object при JSON, string иначе). */
    public function response()
    {
        return $this->Client->response;
    }

    /**
     * Данные rate-limit из заголовков последнего ответа.
     *
     * @return array{limit: int, remaining: int, reset: int, retry: int}
     *   - limit:     максимальное число запросов в окне
     *   - remaining: оставшееся число запросов
     *   - reset:     Unix-timestamp сброса счётчика
     *   - retry:     рекомендуемая задержка в секундах (при 429)
     */
    public function responseRate(): array
    {
        return [
            'limit'     => $this->Client->rateLimit,
            'remaining' => $this->Client->rateRemaining,
            'reset'     => $this->Client->rateReset,
            'retry'     => $this->Client->rateRetry,
        ];
    }

    /** Выполняет GET-запрос; параметры передаются в query string. */
    protected function getRequest(string $path, array $data = [], array $addonHeaders = [])
    {
        return $this->request('GET', $path, $data, $addonHeaders);
    }

    /** Выполняет POST-запрос; параметры сериализуются в JSON-тело. */
    protected function postRequest(string $path, array $data = [], array $addonHeaders = [])
    {
        return $this->request('POST', $path, $data, $addonHeaders);
    }

    /** Выполняет PUT-запрос; параметры сериализуются в JSON-тело. */
    protected function putRequest(string $path, array $data = [], array $addonHeaders = [])
    {
        return $this->request('PUT', $path, $data, $addonHeaders);
    }

    /** Выполняет PATCH-запрос; параметры сериализуются в JSON-тело. */
    protected function patchRequest(string $path, array $data = [], array $addonHeaders = [])
    {
        return $this->request('PATCH', $path, $data, $addonHeaders);
    }

    /** Выполняет DELETE-запрос; параметры сериализуются в JSON-тело. */
    protected function deleteRequest(string $path, array $data = [], array $addonHeaders = [])
    {
        return $this->request('DELETE', $path, $data, $addonHeaders);
    }

    /** Выполняет POST-запрос с multipart/form-data (для загрузки файлов). */
    protected function multipartRequest(string $path, array $data = [], array $addonHeaders = [])
    {
        return $this->request('MULTIPART', $path, $data, $addonHeaders);
    }

    /**
     * Центральный метод выполнения запроса с retry-логикой и обработкой ошибок WB.
     *
     * Обрабатывает следующие коды ответа:
     *   - 400 с текстом "временные ограничения" → ApiTimeRestrictionsException (технический перерыв WB)
     *   - 401 → ApiClientException (неверный или просроченный ключ)
     *   - 429 с текстом "технический перерыв" → ApiTimeRestrictionsException (без retry)
     *   - 429 (другие) → retry до $this->attempts раз с паузой $this->retryDelay мс
     *   - 504 → retry до $this->attempts раз с паузой $this->retryDelay мс
     *
     * По умолчанию: 3 попытки, 5 000 мс задержка.
     */
    private function request(string $method, string $path, array $data = [], array $addonHeaders = [])
    {
        $attempt = 1;

        while (true) {
            $result = $this->Client->request($method, $path, $data, $addonHeaders);

            if (
                $this->responseCode() == 400
                && property_exists($result, 'errorText')
                && mb_strpos(mb_strtolower($result->errorText), 'временные ограничения') !== false
            ) {
                throw new ApiTimeRestrictionsException($result->errorText);

            } elseif ($this->responseCode() == 401) {
                /*
                 * "401 Unauthorized"
                 *
                 * (api-new) can\'t decode supplier key
                 * (api-new) some chars in key are wrong
                 * (api-new) supplier key not found
                 * proxy: invalid token
                 * proxy: unauthorized
                 * request rejected, unathorized
                 */
                if (is_string($result)) {
                    throw new ApiClientException($result, 401);
                } elseif (is_object($result) && property_exists($result, 'errors') && count($result->errors)) {
                    throw new ApiClientException($result->errors[0], 401);
                } else {
                    throw new ApiClientException('Unauthorized', 401);
                }

            } elseif ($this->responseCode() == 429) {
                /*
                 * "429 Too Many Requests"
                 *
                 * { errors: ["Технический перерыв до 16:00"] }
                 * { errors: ["(api-new) too many requests"] }
                 * { code: 429, message: "" }
                 */
                if (property_exists($result, 'errors')) {
                    $message = $result->errors[0];
                } elseif (property_exists($result, 'message')) {
                    $message = $result->message;
                } else {
                    $message = 'Too many requests';
                }

                if (mb_strpos(mb_strtolower($message), 'технический перерыв') !== false) {
                    throw new ApiTimeRestrictionsException($message);
                }

                if ($attempt >= $this->attempts) {
                    throw new ApiClientException($message, 429);
                }

                usleep($this->retryDelay * 1_000);
                $attempt++;
                continue;

            } elseif ($this->responseCode() == 504) {
                /*
                 * "504 Gateway Time-out"
                 */
                if ($attempt >= $this->attempts) {
                    throw new ApiClientException('Gateway Time-out', 504);
                }

                usleep($this->retryDelay * 1_000);
                $attempt++;
                continue;
            }

            return $result;
        }
    }

    /**
     * Валидирует API-ключ если он имеет JWT-формат (три части через точку).
     *
     * Проверяет:
     *   1. Срок действия токена — бросает ApiClientException(401) если истёк.
     *   2. Права доступа к текущему endpoint ($apiName) — бросает ApiClientException(403).
     *
     * Нестандартные ключи (не JWT) пропускаются без ошибки — это нормальная ситуация
     * для тестовых или legacy-токенов WB.
     *
     * @throws ApiClientException При истёкшем токене или отсутствии прав на endpoint
     */
    private function validateToken(string $key): void
    {
        // Не JWT — пропускаем валидацию
        if (substr_count($key, '.') !== 2) {
            return;
        }

        try {
            $token = new APIToken($key);
        } catch (WBSellerException $e) {
            // Похоже на JWT, но не парсится — пропускаем, ошибка вскроется при первом запросе
            return;
        }

        if ($token->isExpired()) {
            throw new ApiClientException(
                sprintf('API токен истёк %s', $token->expireDate()->format('d.m.Y H:i')),
                401
            );
        }

        if ($this->apiName !== '' && !$token->accessTo($this->apiName)) {
            throw new ApiClientException(
                sprintf('Токен не имеет доступа к API "%s"', $this->apiName),
                403
            );
        }
    }
}
