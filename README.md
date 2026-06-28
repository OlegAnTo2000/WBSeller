# WBSeller API
Библиотека для работы с **Wildberries API** [https://openapi.wb.ru](https://openapi.wb.ru)

:memo: [Лог изменений](CHANGELOG.md)

### Установка

Требуется PHP 8.1 или новее.

```bash
composer require oleganto2000/wbseller
```

### Быстрый старт

```php
$wbSellerAPI = new \Dakword\WBSeller\API([
    'masterkey' => 'token',
    'keys' => [
        'content' => 'content_token',
        'marketplace' => 'marketplace_token',
    ],
    'locale' => 'ru',
]);

$contentAPI = $wbSellerAPI->Content();
$response = $contentAPI->getCardsList();
$cards = $response->json();

foreach ($cards->cards as $card) {
    echo $card->vendorCode . PHP_EOL;
}
```

Ключ из `keys` используется для конкретного API. `masterkey` служит fallback, если отдельный ключ не задан.

### Прокси

Прокси задаётся URL-строкой в параметре `proxy`:

```php
$wbSellerAPI = new \Dakword\WBSeller\API([
    'masterkey' => 'token',
    'proxy' => 'http://login:password@127.0.0.1:8080',
]);
```

Поддерживается SOCKS5 с разрешением DNS через прокси:

```php
$wbSellerAPI->setProxy('socks5h://login:password@127.0.0.1:1080');
```

Для интеграционных тестов прокси указывается в `tests/phpunit.xml`:

```xml
<env name="PROXY" value="socks5h://login:password@127.0.0.1:1080" />
```

Формат URL: `scheme://login:password@host:port`. Спецсимволы в логине и пароле должны быть URL-кодированы, например `@` — `%40`, `:` — `%3A`.

### Ответ и обработка ошибок

Все endpoint-методы, выполняющие HTTP-запрос, возвращают immutable `ApiResponse`. Тело ответа декодируется явно:

```php
use Dakword\WBSeller\Exception\ApiClientException;
use Dakword\WBSeller\Exception\ApiResponseDecodingException;
use Dakword\WBSeller\Exception\ApiTransportException;
use Dakword\WBSeller\Exception\LocalTokenValidationException;

try {
    $content = $wbSellerAPI->Content();
    $response = $content->getCardsList();
    $cards = $response->json();

    echo $response->statusCode;
    echo $response->rateLimit->remaining;
    echo $response->headerLine('X-Ratelimit-Limit');
    echo $response->text(); // исходное тело без декодирования
} catch (LocalTokenValidationException $exception) {
    // Локально обнаружены неверные claims, истёкший токен или отсутствие прав.
    echo $exception->getMessage();
} catch (ApiClientException $exception) {
    // Сервер Wildberries вернул HTTP 4xx/5xx.
    echo $exception->statusCode();
    var_dump($exception->response()?->json());
} catch (ApiResponseDecodingException $exception) {
    // Ответ не является корректным JSON. Исходное тело доступно через text().
    echo $exception->getMessage();
} catch (ApiTransportException $exception) {
    // Timeout, DNS, соединение или ошибка middleware.
    echo $exception->getMessage();
}
```

`ApiResponse` предоставляет `text()`, `json()`, `isEmpty()`, `header()`, `headerLine()` и `isSuccessful()`. Метаданные доступны через readonly-свойства `statusCode`, `reasonPhrase`, `headers` и `rateLimit`. `json()` возвращает `null` для пустого тела и бросает `ApiResponseDecodingException` для невалидного JSON.

Локальная проверка токена не проверяет подпись и отзыв токена — окончательную аутентификацию всегда выполняет сервер Wildberries.

### Retry

Retry по умолчанию отключён. Он включается отдельно для экземпляра endpoint:

```php
$response = $wbSellerAPI
    ->Marketplace()
    ->retryOnTooManyRequests(attempts: 3, delay: 2_000)
    ->getOrders();
$orders = $response->json();
```

Автоматический повтор выполняется для `429` и `504`. `GET` считается безопасным по умолчанию. `POST`, `PUT`, `PATCH`, `DELETE` и multipart не повторяются без явного разрешения.

При добавлении endpoint-метода, который читает данные через `POST`, используйте `Retryable`. Для явного запрета повтора, включая нестандартный `GET` с побочным эффектом, используйте `NonRetryable`:

```php
use Dakword\WBSeller\API\Attribute\NonRetryable;
use Dakword\WBSeller\API\Attribute\Retryable;
use Dakword\WBSeller\API\Response\ApiResponse;

#[Retryable]
public function report(array $filter): ApiResponse
{
    return $this->postRequest('/api/v1/report', $filter);
}

#[NonRetryable]
public function runAction(): ApiResponse
{
    return $this->getRequest('/api/v1/action');
}
```

### Наблюдаемость и middleware

```php
$wbSellerAPI
    ->onRequest(static function (array $event): void {
        // Запрос подготовлен. Authorization уже замаскирован.
    })
    ->onResponse(static function (array $event): void {
        // Успешный HTTP-ответ.
    })
    ->onError(static function (array $event): void {
        // HTTP- или транспортная ошибка.
    })
    ->onListenerError(static function (array $event): void {
        error_log($event['event'] . ': ' . $event['message']);
    });

$wbSellerAPI->addMiddleware(
    static fn(callable $handler): callable => $handler,
    'middleware_name',
);
```

Listeners выполняются в порядке регистрации. Их исключения не прерывают запрос и передаются в `onListenerError`; без обработчика библиотека использует стандартный `error_log()` PHP. Первый зарегистрированный middleware оборачивает добавленные после него.

### Разработка

```bash
composer test
composer phpstan
composer test:integration
```

Unit-тесты не требуют реального API-ключа. Интеграционные тесты запускаются отдельно и используют переменные окружения из `tests/phpunit.dist.xml`.

### Поддерживаемые API
:book: [Документация](/docs/API.md)

| API                   | Endpoint                                                 |
| --------------------- | -------------------------------------------------------- |
| Общее                 | $wbSellerAPI->[**Common()**](/docs/Common.md)            |
| Контент               | $wbSellerAPI->[**Content()**](/docs/Content.md)          |
| Цены и скидки         | $wbSellerAPI->[**Prices()**](/docs/Prices.md)            |
| Маркетплейс           | $wbSellerAPI->[**Marketplace()**](/docs/Marketplace.md)  |
| Статистика            | $wbSellerAPI->[**Statistics()**](/docs/Statistics.md)    |
| Аналитика             | $wbSellerAPI->[**Analytics()**](/docs/Analytics.md)      |
| Продвижение           | $wbSellerAPI->[**Adv()**](/docs/Adv.md)                  |
| Рекомендации          | $wbSellerAPI->[**Recommends()**](/docs/Recommends.md)    |
| Вопросы               | $wbSellerAPI->[**Questions()**](/docs/Questions.md)      |
| Отзывы                | $wbSellerAPI->[**Feedbacks()**](/docs/Feedbacks.md)      |
| Тарифы                | $wbSellerAPI->[**Tariffs()**](/docs/Tariffs.md)          |
| Чат с покупателями    | $wbSellerAPI->[**Chat()**](/docs/Chat.md)                |
| Возвраты покупателями | $wbSellerAPI->[**Returns()**](/docs/Returns.md)          |
| Документы             | $wbSellerAPI->[**Documents()**](/docs/Documents.md)      |
| Календарь акций       | $wbSellerAPI->[**Calendar()**](/docs/Calendar.md)        |
| Поставки              | $wbSellerAPI->[**Supplies()**](/docs/Supplies.md)        |

### Декодирование токена

```php
try {
    $token = new \Dakword\WBSeller\APIToken('eyJhbGciOiJFUzI1NiIs...');
} catch (\Dakword\WBSeller\Exception\WBSellerException $exc) {
    echo $exc->getMessage(); // Неверный формат токена
}

// Отображение
echo $token->masked();        // eyJhb...s...' — безопасное отображение токена
echo $token;                  // eyJhbGciOiJFUzI1NiIs... — полный токен

// Срок действия
echo $token->expireDate()->format('Y-m-d H:i:s'); // 2024-09-20 16:21:04
echo $token->isExpired() ? 'Просроченный' : 'Действительный';
echo $token->daysUntilExpiry();  // Через сколько полных дней истекает (отрицательное — уже истёк)
echo $token->hoursUntilExpiry(); // То же в полных часах

// Тип токена
echo $token->tokenType();       // 1=Базовый, 2=Тестовый, 3=Персональный, 4=Сервисный
echo $token->isBasic()    ? 'Базовый'      : '';
echo $token->isTest()     ? 'Тестовый'     : '';
echo $token->isPersonal() ? 'Персональный' : '';
echo $token->isService()  ? 'Сервисный'    : '';
echo $token->serviceId(); // ID сервиса для сервисного токена, иначе null
echo $token->isReadOnly() ? 'Только чтение' : 'Чтение и запись';

// Продавец
echo $token->sellerId();   // 284034 (числовой ID продавца, если присутствует в токене)
echo $token->sellerUUID(); // 123e4567-e89b-12d3-a456-426655440000

// Доступ к категориям API
echo $token->accessTo('marketplace') ? 'Yes' : 'No';            // проверка одной категории
echo $token->hasAccess('marketplace', 'content') ? 'Yes' : 'No'; // проверка нескольких сразу
echo implode(', ', $token->accessList());                         // 'Цены и скидки, Маркетплейс, Документы'
echo implode(', ', array_keys($token->accessList()));             // '3, 4, 12' — позиции бита

print_r($token->getPayload());
```

### Примеры использования WBSeller API

```php
$wbSellerAPI = new \Dakword\WBSeller\API([
    'keys' => [
        'content' => 'Content_key',
        'feedbacks' => 'FB_key',
        'marketplace' => 'Marketplace_key',
        'questions' => 'FB_key',
    ],
    'masterkey' => 'multi_key', // 'content' + 'prices' + ...
    'apiurls' => [
        'content'         => 'https://suppliers-api.wb.ru',
        'feedbacks'       => 'https://feedbacks-api.wildberries.ru',
        'adv'             => 'https://advert-api-sandbox.wildberries.ru',
        'analytics'       => 'https://abc.site.ru',
    ],
    'locale' => 'ru'
]);

// Proxy
$wbSellerAPI->useProxy('http://122.123.123.123:8088');
// Locale
$wbSellerAPI->setLocale('en');

$contentApi = $wbSellerAPI->Content();
$pricesApi = $wbSellerAPI->Prices();
$marketApi = $wbSellerAPI->Marketplace();

// subAPI контента - теги
$tagsApi = $wbSellerAPI->Content()->Tags();

// Получить список НМ
$result = $contentApi->getCardsList()->json();
if (!$result->error) {
    var_dump($result->cards, $result->cursor);
}

// Получение информации по ценам и скидкам
$info = $pricesApi->getPrices()->json();
var_dump($info);

// Cписок складов поставщика
$warehouses = $wbSellerAPI->Marketplace()->Warehouses()->list()->json();
var_dump($warehouses);

// Заказы FBS (С автоповтором запросов 💡)
$orders = $marketApi->retryOnTooManyRequests(10, 1000)->getOrders()->json();
var_dump($orders);

// Создание КТ
try {
    $createCardResult = $contentApi->createCard([
        'subjectID' => 105,
        'variants' => [
            [
                'vendorCode' => 'A0001',
                'title' => 'Наименование',
                'description' => 'Описание',
                'brand' => 'Бренд',
                'dimensions' => [
                    'length' => 55,
                    'width' => 40,
                    'height' => 15,
                ],
                'characteristics' => [
                    [
                        'id' => 12,
                        'value' => 'свободный крой',
                    ],
                    [
                        'id' => 88952,
                        'value' => 200,
                    ],
                    [
                        'id' => 14177449,
                        'value' => ['red'],
                    ],
                ],
                'sizes' => [
                    [
                        'techSize' => '39',
                        'wbSize' => '',
                        'price' => (int) 3999.99,
                        'skus' => [ '1000000001' ]
                    ]
                ],
            ],
        ]
    ])->json();
    if ($createCardResult->error) {
        echo 'Ошибка создания карточки: ' . $createCardResult->errorText;
    } else {
        echo 'Запрос на создание карточки отправлен';
    }
} catch (\Dakword\WBSeller\Exception\WBSellerException $exc) {
    echo 'Исключение при создании карточки: ' . $exc->getMessage();
}
```
