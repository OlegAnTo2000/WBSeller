## WBSeller API
Библиотека для работы с [Wildberries API](https://openapi.wb.ru)

```php
$wbSellerAPI = new \Dakword\WBSeller\API($options = [
    'masterkey' => 'token',
    'keys' => [
        'content' => 'content_token'
    ],
    //'apiurls' => [
    //    'content' => 'https://suppliers-api.wb.ru'
    //],
    //'locale' => 'ru'
]);
```

### Ограничение частоты запросов

При превышении допустимой частоты запросов Wildberries API возвращает ответ `429 Too Many Requests`. Повторять запрос следует после задержки, указанной в заголовках ответа:

- `X-Ratelimit-Retry` — через сколько секунд можно повторить запрос. Более ранняя попытка также завершится ошибкой `429`;
- `X-Ratelimit-Limit` — максимальный всплеск запросов (burst), который восстановится через количество секунд из `X-Ratelimit-Reset`;
- `X-Ratelimit-Reset` — через сколько секунд допустимый всплеск запросов восстановится до значения `X-Ratelimit-Limit`.

Пример ответа:

```http
HTTP/1.1 429 Too Many Requests
X-Ratelimit-Reset: 29
X-Ratelimit-Retry: 2
X-Ratelimit-Limit: 10
```

Автоматический retry разрешён для `GET`. Для остальных HTTP-методов он отключён, чтобы повтор изменяющего запроса не создал дублирующие изменения. Если `POST` фактически только получает данные, пометьте публичный endpoint-метод атрибутом `#[\Dakword\WBSeller\API\Attribute\Retryable]`. Атрибут `#[\Dakword\WBSeller\API\Attribute\NonRetryable]` явно запрещает retry, в том числе для `GET`.

### Проверка токена

До отправки запроса библиотека локально декодирует JWT claims и проверяет формат payload, срок действия `exp` и маску доступа `s`. Ошибка этой проверки представлена `LocalTokenValidationException`.

Локальная проверка не подтверждает подпись токена, его отзыв, актуальность прав или доступность аккаунта. Окончательное решение об аутентификации принимает сервер Wildberries. Его ответы `401` и `403` представлены `ApiClientException`.

Строки, не похожие на JWT из трёх частей, локально не проверяются и передаются серверу как есть.

### Ошибки запросов

Все ответы `4xx` и `5xx` представлены `ApiClientException` независимо от того, содержит тело JSON, обычный текст или пустую строку. Исходный `ApiResponse` доступен через `response()`, а исходное Guzzle-исключение — через `getPrevious()`.

Сетевые ошибки и исключения middleware представлены `ApiTransportException`. Все исключения транспорта библиотеки наследуют `WBSellerException`, поэтому для общего обработчика достаточно перехватить этот базовый класс.

Все HTTP-методы endpoint возвращают immutable `ApiResponse` без преобразования тела. Исходное тело читается через `text()`, JSON — через `json()`, заголовки — через `header()` и `headerLine()`. Также доступны `isEmpty()`, `isSuccessful()`, `statusCode`, `reasonPhrase`, `headers` и `rateLimit`. Невалидный JSON приводит к `ApiResponseDecodingException`.

#### Миграция на 5.0.0

| Раньше | Теперь |
| --- | --- |
| `$data = $endpoint->method()` | `$data = $endpoint->method()->json()` |
| `$text = $endpoint->method()` | `$text = $endpoint->method()->text()` |
| `$ok = $endpoint->method()` | `$ok = $endpoint->method()->isSuccessful()` |
| `$endpoint->responseCode()` | `$response->statusCode` |
| `$endpoint->responseRate()` | `$response->rateLimit` |

Методы `lastResponse()`, `responseCode()`, `response()`, `responsePhrase()`, `responseHeaders()`, `rawResponse()` и `responseRate()` удалены. Ответ конкретного запроса необходимо сохранять непосредственно.

Исключения из `request`, `response` и `error` listeners не прерывают HTTP-запрос, но больше не теряются. Их можно получить через `$api->onListenerError($callback)` или секцию `listeners.listener_error`. Если обработчик не зарегистрирован, сообщение записывается через стандартный `error_log()` PHP.

### Поддерживаемые API

| API | Endpoint | $options['keys' / 'apiurls']['?'] | 'apiurls' defaults |
| --- | -------- | --------------------------------- | ------------------ |
| Общее                    | $wbSellerAPI->[**Common()**](Common.md)           | сommon          | https://common-api.wildberries.ru
| Контент                  | $wbSellerAPI->[**Content()**](Content.md)         | content         | https://suppliers-api.wildberries.ru
| Цены и скидки            | $wbSellerAPI->[**Prices()**](Prices.md)           | prices          | https://discounts-prices-api.wildberries.ru
| Маркетплейс              | $wbSellerAPI->[**Marketplace()**](Marketplace.md) | marketplace     | https://marketplace-api.wildberries.ru
| Статистика               | $wbSellerAPI->[**Statistics()**](Statistics.md)   | statistics      | https://statistics-api.wildberries.ru
| Аналитика                | $wbSellerAPI->[**Analytics()**](Analytics.md)     | analytics       | https://seller-analytics-api.wildberries.ru
| Продвижение              | $wbSellerAPI->[**Adv()**](Adv.md)                 | adv             | https://advert-api.wildberries.ru
| Рекомендации             | $wbSellerAPI->[**Recommends()**](Recommends.md)   | recommends      | https://recommend-api.wildberries.ru
| Вопросы                  | $wbSellerAPI->[**Questions()**](Questions.md)     | feedbacks       | https://feedbacks-api.wildberries.ru
| Отзывы                   | $wbSellerAPI->[**Feedbacks()**](Feedbacks.md)     | feedbacks       | https://feedbacks-api.wildberries.ru
| Тарифы                   | $wbSellerAPI->[**Tariffs()**](Tariffs.md)         | tariffs         | https://common-api.wildberries.ru
| Чат<br>с покупателями    | $wbSellerAPI->[**Chat()**](Chat.md)               | chat            | https://buyer-chat-api.wildberries.ru
| Возвраты<br>покупателями | $wbSellerAPI->[**Returns()**](Returns.md)         | returns         | https://returns-api.wildberries.ru
| Документы                | $wbSellerAPI->[**Documents()**](Documents.md)     | documents       | https://documents-api.wildberries.ru
| Календарь акций          | $wbSellerAPI->[**Calendar()**](Calendar.md)       | prices/calendar | https://dp-calendar-api.wildberries.ru
| Поставки                 | $wbSellerAPI->[**Supplies()**](Supplies.md)       | supplies        | https://supplies-api.wildberries.ru
