## [WBSeller API](/docs/API.md) / Common()

```php
$wbSellerAPI = new \Dakword\WBSeller\API($options);
$Common = $wbSellerAPI->Common();
```

Wildberries API / **Общее**

| :speech_balloon: | :cloud: | [Common()](/src/API/Endpoint/Common.php) |
| ---------------- | ------- | ---------------------------------------- |
| Проверка подключения к API      | /ping               | Common()->**ping()**       |
| Получение информации о продавце | /api/v1/seller-info | Common()->**sellerInfo()** |
| Получение подписки «Джем» | /api/common/v1/subscriptions | Common()->**subscriptions()** |
| Опции конструктора тарифов | /api/common/v1/tariff-constructor/options | Common()->**tariffConstructorOptions()** |
<br>

## [WBSeller API](/docs/API.md) / Common()->News()

```php
$wbSellerAPI = new \Dakword\WBSeller\API($options);
$Common = $wbSellerAPI->Common();
$News = $Common->News();
```
Wildberries API / [**Новости портала поставщиков**](https://openapi.wb.ru/general/sellers_portal_news/ru/)

| :speech_balloon: | :cloud: | [News()](/src/API/Endpoint/Subpoint/News.php) |
| ---------------- | ------- | --------------------------------------------- |
| Новости с даты или ID | /api/communications/v2/news | News()->**list()** |

Методы `fromDate()` и `fromId()` для маршрута v1 сохранены для обратной совместимости и помечены устаревшими.

## Другие методы раздела «Общее»

Операции из Swagger, размещённые на отдельных доменах WB:

| :speech_balloon: | :cloud: | Вызов |
| ---------------- | ------- | ----- |
| Рейтинг продавца | /api/common/v1/rating | Feedbacks()->**sellerRating()** |
| Пригласить пользователя | /api/v1/invite | Users()->**invite()** |
| Получить пользователей | /api/v1/users | Users()->**list()** |
| Изменить права пользователей | /api/v1/users/access | Users()->**updateAccess()** |
| Закрыть доступ пользователю | /api/v1/user | Users()->**delete()** |
