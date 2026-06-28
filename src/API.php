<?php

declare(strict_types=1);

namespace Dakword\WBSeller;

use Dakword\WBSeller\API\Endpoint\{ Adv, Analytics, Calendar, Chat, Common, Content, Documents, Feedbacks, Marketplace, Prices, Questions, Recommends, Returns, Statistics, Supplies, Tariffs };
use Dakword\WBSeller\API\Endpoint\Test;

/**
 * Главная точка входа в библиотеку WBSeller.
 *
 * Хранит конфигурацию (ключи, прокси, locale, middlewares, listeners) и
 * создаёт endpoint-объекты по запросу. Каждый вызов метода-фабрики
 * (Adv(), Content(), …) создаёт новый экземпляр endpoint — они не кешируются,
 * поэтому можно получить несколько независимых клиентов с разными ключами/прокси.
 *
 * Ключи API можно задать двумя способами:
 *   1. Точечно: `keys['content'] => '...'` — используется только для указанного endpoint.
 *   2. Универсально: `masterkey => '...'` — fallback для всех endpoint без явного ключа.
 *
 * Порядок разрешения ключа: точечный > masterkey.
 *
 * Locale по умолчанию: 'ru'. Можно переопределить через env-переменную
 * `WBSELLER_LOCALE` или через параметр `locale` в конструкторе / setLocale().
 *
 * @see AbstractEndpoint — базовый класс всех endpoint
 */
class API
{
    private array $apiUrls = [
        'adv'         => 'https://advert-api.wildberries.ru',
        'analytics'   => 'https://seller-analytics-api.wildberries.ru',
        'calendar'    => 'https://dp-calendar-api.wildberries.ru',
        'chat'        => 'https://buyer-chat-api.wildberries.ru',
        'common'      => 'https://common-api.wildberries.ru',
        'content'     => 'https://content-api.wildberries.ru',
        'documents'   => 'https://documents-api.wildberries.ru',
        'feedbacks'   => 'https://feedbacks-api.wildberries.ru',
        'marketplace' => 'https://marketplace-api.wildberries.ru',
        'prices'      => 'https://discounts-prices-api.wildberries.ru',
        'questions'   => 'https://feedbacks-api.wildberries.ru',
        'recommends'  => 'https://recommend-api.wildberries.ru',
        'returns'     => 'https://returns-api.wildberries.ru',
        'statistics'  => 'https://statistics-api.wildberries.ru',
        'supplies'    => 'https://supplies-api.wildberries.ru',
        'tariffs'     => 'https://common-api.wildberries.ru',
    ];
    private array $apiKeys;
    private string $masterKey;
    private string $locale;
    private ?string $proxy = null;
    private bool $verifySsl = true;
    private array $middlewares = [];
    private array $listeners = [
        'request'  => [],
        'response' => [],
        'error'    => [],
        'listener_error' => [],
    ];

    /**
     * Создаёт клиент WB API.
     *
     * Все параметры опциональны — можно вызвать `new API()` и потом допрописать
     * конфигурацию через сеттеры (`useProxy`, `setLocale`, `addMiddleware`).
     *
     * Подводный камень: `calendar` endpoint использует ключ `prices` (не `calendar`)
     * — это особенность API WB, обе группы методов живут на одном разрешении токена.
     *
     * @param array $options [
     *  'keys' => [
     *     'adv' => '',
     *     'analytics' => '',
     *     'content' => 'Content_key',
     *     'feedbacks' => 'FB_key',
     *     'marketplace' => 'Marketplace_key',
     *     'prices' => '',
     *     'questions' => 'FB_key',
     *     'recommends' => '',
     *     'statistics' => '',
     *     'tariffs' => '',
     *   ],
     *   'masterkey' => 'alternative_universal_key',
     *   'apiurls' => [
     *     'adv' => 'url',
     *     'analytics' => '',
     *     'content' => 'url',
     *     'feedbacks' => 'url',
     *     'marketplace' => '',
     *     'prices' => '',
     *     'questions' => '',
     *     'recommends' => '',
     *     'statistics' => '',
     *     'tariffs' => '',
     *   ],
     *   'locale' => 'ru',
     *   'middlewares' => [
     *     'middleware_name' => callable,
     *     callable,
     *   ],
     *   'listeners' => [
     *     'request' => [callable],
     *     'response' => [callable],
     *     'error' => [callable],
     *     'listener_error' => [callable],
     *   ],
     *   'proxy' => 'http://122.123.123.123:8088',
     *   'ssl_verify' => true,
     * ]
     */
    function __construct(array $options = [])
    {
        $this->apiKeys   = $options['keys'] ?? [];
        $this->masterKey = $options['masterkey'] ?? '';

        $locale = $options['locale'] ?? null;
        $this->setLocale(!is_null($locale) ? $locale : (getenv('WBSELLER_LOCALE') ?: 'ru'));

        if (isset($options['apiurls']) && is_array($options['apiurls'])) {
            foreach ($options['apiurls'] as $apiName => $apiUrl) {
                $arrayKey = strtolower($apiName);
                if (array_key_exists($arrayKey, $this->apiUrls)) {
                    $this->apiUrls[$arrayKey] = rtrim($apiUrl, '/');
                }
            }
        }

        if (array_key_exists('middlewares', $options)) {
            if (!is_array($options['middlewares'])) {
                throw new \InvalidArgumentException('Параметр middlewares должен быть массивом');
            }
            foreach ($options['middlewares'] as $middleware) {
                if (!is_callable($middleware)) {
                    throw new \InvalidArgumentException('Каждый middleware должен быть callable');
                }
            }
            $this->middlewares = $options['middlewares'];
        }

        if (array_key_exists('listeners', $options)) {
            $this->listeners = $this->validateListeners($options['listeners']);
        }

        if (isset($options['proxy']) && is_string($options['proxy'])) {
            $this->useProxy($options['proxy']);
        }

        if (isset($options['ssl_verify']) && is_bool($options['ssl_verify'])) {
            $this->verifySsl = $options['ssl_verify'];
        }
    }

    /**
     * Включить или отключить проверку SSL-сертификата сервера.
     *
     * По умолчанию включена (true). Отключайте только в крайних случаях
     * (например, при работе через корпоративный прокси с self-signed сертификатом).
     * Отключение SSL делает соединение уязвимым к MITM-атакам.
     */
    public function setSslVerify(bool $verify): self
    {
        $this->verifySsl = $verify;
        return $this;
    }

    /**
     * Регистрирует callback, который вызывается перед каждым HTTP-запросом.
     *
     * Callback получает массив: ['id', 'method', 'url', 'headers', 'params'].
     * Заголовок Authorization в `headers` уже замаскирован ('***masked***').
     * Можно добавить несколько слушателей — они вызываются в порядке добавления.
     */
    public function onRequest(callable $cb): self
    {
        $this->listeners['request'][] = $cb;
        return $this;
    }

    /**
     * Регистрирует callback, который вызывается после каждого успешного HTTP-ответа.
     *
     * Callback получает массив: ['request_id', 'method', 'url', 'status', 'headers', 'raw', 'duration_ms'].
     * Для HTTP-ошибок (4xx/5xx) вызывается onError, а не onResponse.
     */
    public function onResponse(callable $cb): self
    {
        $this->listeners['response'][] = $cb;
        return $this;
    }

    /**
     * Регистрирует callback для HTTP-ошибок и сетевых сбоев.
     *
     * Callback получает массив: ['request_id', 'method', 'url', 'status', 'raw',
     * 'duration_ms', 'exception_class', 'message'].
     * Вызывается как при 4xx/5xx ответах, так и при сетевых исключениях (timeout, DNS).
     * Ошибки внутри callback передаются обработчикам onListenerError.
     */
    public function onError(callable $cb): self
    {
        $this->listeners['error'][] = $cb;
        return $this;
    }

    /**
     * Регистрирует обработчик исключений, возникших внутри других listeners.
     * Callback получает event, listener_index, exception_class, message и exception.
     */
    public function onListenerError(callable $cb): self
    {
        $this->listeners['listener_error'][] = $cb;
        return $this;
    }

    /**
     * Возвращает все зарегистрированные listeners для передачи в endpoint-объекты.
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }

    private function validateListeners(mixed $listeners): array
    {
        if (!is_array($listeners)) {
            throw new \InvalidArgumentException('Параметр listeners должен быть массивом');
        }

        $validated = ['request' => [], 'response' => [], 'error' => [], 'listener_error' => []];
        foreach ($listeners as $event => $callbacks) {
            if (!is_string($event) || !array_key_exists($event, $validated)) {
                throw new \InvalidArgumentException('Неизвестный тип listener: ' . (string) $event);
            }
            if (!is_array($callbacks)) {
                throw new \InvalidArgumentException(sprintf('Listeners "%s" должны быть массивом', $event));
            }
            foreach ($callbacks as $callback) {
                if (!is_callable($callback)) {
                    throw new \InvalidArgumentException(sprintf('Каждый listener "%s" должен быть callable', $event));
                }
            }
            $validated[$event] = $callbacks;
        }

        return $validated;
    }

    /**
     * Разрешает API-ключ для указанного endpoint.
     *
     * Возвращает точечный ключ из `keys[$keyName]`, если он задан и не пустой.
     * Иначе возвращает `masterKey` как универсальный fallback.
     */
    private function getKey($keyName): string
    {
        return isset($this->apiKeys[$keyName]) && is_string($this->apiKeys[$keyName]) && $this->apiKeys[$keyName] !== ''
            ? $this->apiKeys[$keyName]
            : $this->masterKey;
    }

    /**
     * Использовать для запросов прокси
     *
     * @param string $proxyUrl http://username:password@192.168.16.1:10
     * @return self
     */
    public function useProxy(string $proxyUrl): self
    {
        $this->proxy = $proxyUrl;
        return $this;
    }

    /**
     * Установить прокси URL, аналог useProxy()
     *
     * @param string|null $proxyUrl http://username:password@192.168.16.1:10
     * @return self
     */
    public function setProxy(?string $proxyUrl): self
    {
        return $this->useProxy($proxyUrl);
    }

    /**
     * Добавить middleware для всех endpoint
     *
     * @param callable $middleware Middleware функция
     * @param string $name Имя middleware (опционально)
     * @return self
     */
    public function addMiddleware(callable $middleware, string $name = ''): self
    {
        if ($name !== '') {
            $this->middlewares[$name] = $middleware;
        } else {
            $this->middlewares[] = $middleware;
        }
        return $this;
    }

    public function getProxy()
    {
        return $this->proxy;
    }

    /**
     * Устанавливает язык ответов API (например 'ru', 'en').
     *
     * Locale передаётся в каждый создаваемый endpoint и влияет на тексты
     * категорий, характеристик и других справочников WB.
     * По умолчанию 'ru'; может быть переопределена через env WBSELLER_LOCALE.
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Переопределяет базовый URL для конкретного endpoint.
     *
     * Полезно для тестирования через mock-сервер или при использовании
     * локального прокси, который проксирует запросы к WB.
     * Имя `$apiName` регистронезависимо; неизвестные имена молча игнорируются.
     */
    public function setApiUrl(string $apiName, string $apiUrl): self
    {
        $arrayKey = strtolower($apiName);
        if (array_key_exists($arrayKey, $this->apiUrls)) {
            $this->apiUrls[$arrayKey] = rtrim($apiUrl, '/');
        }
        return $this;
    }

    /**
     * Создаёт клиент рекламного API (advert-api.wildberries.ru).
     *
     * Включает управление кампаниями (авто, поиск, каталог, медиа),
     * ставками, бюджетами и статистикой рекламы.
     * Ключ разрешается из `keys['adv']` или masterKey.
     */
    public function Adv(): Adv
    {
        return new Adv(
            $this->apiUrls['adv'],
            $this->getKey('adv'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент аналитического API (seller-analytics-api.wildberries.ru).
     *
     * Воронка продаж, доля брендов, складские остатки, заблокированные товары,
     * антифрод, региональные продажи, платное хранение.
     */
    public function Analytics(): Analytics
    {
        return new Analytics(
            $this->apiUrls['analytics'],
            $this->getKey('analytics'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент API акций и промо-календаря (dp-calendar-api.wildberries.ru).
     *
     * Внимание: использует ключ `prices` (а не `calendar`) — в токене WB
     * акции и цены/скидки относятся к одной группе разрешений (бит 3).
     */
    public function Calendar(): Calendar
    {
        return new Calendar(
            $this->apiUrls['calendar'],
            $this->getKey('prices'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент чата с покупателями (buyer-chat-api.wildberries.ru).
     *
     * Получение списка чатов, отправка текстовых сообщений и вложений
     * через multipart-запросы.
     */
    public function Chat(): Chat
    {
        return new Chat(
            $this->apiUrls['chat'],
            $this->getKey('chat'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент общего API (common-api.wildberries.ru).
     *
     * Информация о продавце, новости WB. Тот же базовый URL используется
     * для Tariffs — это нормально, они работают на разных путях.
     * Ключ `common` не требует специального разрешения в токене (бит 0).
     */
    public function Common(): Common
    {
        return new Common(
            $this->apiUrls['common'],
            $this->getKey('common'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент API контента (content-api.wildberries.ru).
     *
     * Создание и редактирование карточек товаров, управление номенклатурой,
     * баркодами, медиафайлами, тегами, корзиной, справочником категорий.
     */
    public function Content(): Content
    {
        return new Content(
            $this->apiUrls['content'],
            $this->getKey('content'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент API документов (documents-api.wildberries.ru).
     *
     * Получение списка категорий документов, загрузка одного или нескольких
     * документов в разных форматах (PDF, XLSX и др.).
     */
    public function Documents(): Documents
    {
        return new Documents(
            $this->apiUrls['documents'],
            $this->getKey('documents'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент API отзывов (feedbacks-api.wildberries.ru).
     *
     * Работа с отзывами покупателей: получение, ответы, оценки, XLSX-экспорт,
     * шаблоны ответов. Тот же базовый URL используется для Questions.
     */
    public function Feedbacks(): Feedbacks
    {
        return new Feedbacks(
            $this->apiUrls['feedbacks'],
            $this->getKey('feedbacks'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент маркетплейс API (marketplace-api.wildberries.ru).
     *
     * Поставки, заказы, стикеры, склады продавца, кросс-бордер, DBS,
     * пропуска на склад, курьерская доставка WB.
     */
    public function Marketplace(): Marketplace
    {
        return new Marketplace(
            $this->apiUrls['marketplace'],
            $this->getKey('marketplace'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент API цен и скидок (discounts-prices-api.wildberries.ru).
     *
     * Получение и массовая загрузка цен/скидок по размерам, статус загрузки,
     * карантин товаров, клубные скидки. Тот же ключ используется для Calendar.
     */
    public function Prices(): Prices
    {
        return new Prices(
            $this->apiUrls['prices'],
            $this->getKey('prices'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент API вопросов (feedbacks-api.wildberries.ru).
     *
     * Вопросы покупателей: список, ответы, отклонение, XLSX-экспорт, шаблоны.
     * Базовый URL совпадает с Feedbacks — это одна группа API у WB.
     */
    public function Questions(): Questions
    {
        return new Questions(
            $this->apiUrls['questions'],
            $this->getKey('questions'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент API рекомендаций (recommend-api.wildberries.ru).
     *
     * Управление списком рекомендуемых товаров: получение, добавление,
     * обновление позиций, удаление.
     */
    public function Recommends(): Recommends
    {
        return new Recommends(
            $this->apiUrls['recommends'],
            $this->getKey('recommends'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент API возвратов (returns-api.wildberries.ru).
     *
     * Заявки покупателей на возврат: текущие и архивные. Ответ продавца
     * задаётся через константы ReturnAction (одобрить/отклонить с разными причинами).
     */
    public function Returns(): Returns
    {
        return new Returns(
            $this->apiUrls['returns'],
            $this->getKey('returns'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент API статистики (statistics-api.wildberries.ru).
     *
     * Поставки, остатки, заказы, продажи, возвраты, детализированные отчёты.
     * Внимание: данные доступны с задержкой (обновляются не в реальном времени).
     */
    public function Statistics(): Statistics
    {
        return new Statistics(
            $this->apiUrls['statistics'],
            $this->getKey('statistics'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент API поставок (supplies-api.wildberries.ru).
     *
     * Коэффициенты приёмки, допустимые склады для поставки, список складов WB.
     */
    public function Supplies(): Supplies
    {
        return new Supplies(
            $this->apiUrls['supplies'],
            $this->getKey('supplies'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт клиент API тарифов (common-api.wildberries.ru).
     *
     * Комиссии по категориям, тарифы доставки коробом и паллетой, тарифы возврата.
     * Базовый URL совпадает с Common.
     */
    public function Tariffs(): Tariffs
    {
        return new Tariffs(
            $this->apiUrls['tariffs'],
            $this->getKey('tariffs'),
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }

    /**
     * Создаёт тестовый endpoint для отправки произвольных HTTP-запросов.
     *
     * Базовый URL пустой — путь к запросу должен содержать полный URL.
     * Использует masterKey. Предназначен для отладки прокси и нестандартных запросов.
     * Не привязан к конкретному API WB.
     */
    public function Test(): Test
    {
        return new Test(
            '',
            $this->masterKey,
            $this->proxy,
            $this->locale,
            $this->middlewares,
            $this->listeners,
            $this->verifySsl
        );
    }
}
