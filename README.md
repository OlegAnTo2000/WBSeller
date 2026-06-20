# WBSeller API
Библиотека для работы с **Wildberries API** [https://openapi.wb.ru](https://openapi.wb.ru)

:memo: [Лог изменений](CHANGELOG.md)

```php
$wbSellerAPI = new \Dakword\WBSeller\API($options = [
    'masterkey' => 'token',
    //'keys' => [...],
    //'apiurls' => [...],
    //'locale' => 'ru'
]);
// API Контента
$contentAPI = $wbSellerAPI->Content();
$contentAPI->getCardsList(); // Получить список карточек
```

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
$result = $contentApi->getCardsList();
if (!$result->error) {
    var_dump($result->cards, $result->cursor);
}

// Получение информации по ценам и скидкам
$info = $pricesApi->getPrices();
var_dump($info);

// Cписок складов поставщика
$warehouses = $wbSellerAPI->Marketplace()->Warehouses()->list();
var_dump($warehouses);

// Заказы FBS (С автоповтором запросов 💡)
$orders = $marketApi->retryOnTooManyRequests(10, 1000)->getOrders();
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
    ]);
    if ($createCardResult->error) {
        echo 'Ошибка создания карточки: ' . $createCardResult->errorText;
    } else {
        echo 'Запрос на создание карточки отправлен';
    }
} catch (\Dakword\WBSeller\Exception\WBSellerException $exc) {
    echo 'Исключение при создании карточки: ' . $exc->getMessage();
}
```
