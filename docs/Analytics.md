## [WBSeller API](/docs/API.md) / Analytics()

```php
$wbSellerAPI = new \Dakword\WBSeller\API($options);
$Analytics = $wbSellerAPI->Analytics();
```

Wildberries API / [**Аналитика**](https://openapi.wb.ru/analytics/api/ru/)

| :speech_balloon: | :cloud: | [Analytics()](/src/API/Endpoint/Analytics.php) |
| ---------------- | ------- | ---------------------------------------------- |
| Проверка подключения к API | /ping | Analytics()->**ping()** |
| **Воронка продаж v3** |||
| Статистика карточек товаров | /api/analytics/v3/sales-funnel/products | Analytics()->**v3SalesFunnelProducts()** |
| История по артикулам WB | /api/analytics/v3/sales-funnel/products/history | Analytics()->**v3SalesFunnelProductsHistory()** |
| История по группам | /api/analytics/v3/sales-funnel/grouped/history | Analytics()->**v3SalesFunnelGroupedHistory()** |
| **CSV-отчёты** |||
| Создать отчёт | POST /api/v2/nm-report/downloads | Analytics()->**createAnalyticsReport()** |
| Получить список отчётов | GET /api/v2/nm-report/downloads | Analytics()->**getAnalyticsReports()** |
| Повторить генерацию | /api/v2/nm-report/downloads/retry | Analytics()->**retryAnalyticsReport()** |
| Скачать файл | /api/v2/nm-report/downloads/file/{downloadId} | Analytics()->**downloadAnalyticsReportFile()** |
| **Поисковые запросы** |||
| Главная страница | /api/v2/search-report/report | Analytics()->**searchReport()** |
| Группы | /api/v2/search-report/table/groups | Analytics()->**searchReportGroups()** |
| Товары в группе | /api/v2/search-report/table/details | Analytics()->**searchReportDetails()** |
| Тексты поисковых запросов | /api/v2/search-report/product/search-texts | Analytics()->**searchReportProductSearchTexts()** |
| Заказы по поисковым запросам | /api/v2/search-report/product/orders | Analytics()->**searchReportProductOrders()** |
| **История остатков** |||
| Склады WB | /api/analytics/v1/stocks-report/wb-warehouses | Analytics()->**stocksReportWbWarehouses()** |
| Группы товаров | /api/v2/stocks-report/products/groups | Analytics()->**stocksReportProductGroups()** |
| Товары | /api/v2/stocks-report/products/products | Analytics()->**stocksReportProducts()** |
| Размеры | /api/v2/stocks-report/products/sizes | Analytics()->**stocksReportProductSizes()** |
| Склады | /api/v2/stocks-report/offices | Analytics()->**stocksReportOffices()** |
| **Рейтинг карточки товара** |||
| Актуальный метод | /api/analytics/v2/item-rating | Analytics()->**itemRating()** |
| Устаревший метод | /api/analytics/v1/item-rating | Analytics()->**itemRatingV1()** |
<br>

Методы, которых нет в `11-analytics.yaml`, сохранены для обратной совместимости и помечены `@deprecated`.

## [WBSeller API](/docs/API.md) / Analytics()->PaidStorage()

```php
$wbSellerAPI = new \Dakword\WBSeller\API($options);
$PaidStorage = $wbSellerAPI->Analytics()->PaidStorage();
```
Wildberries API Аналитика / [**Платное хранение**](https://openapi.wb.ru/analytics/api/ru/#tag/Platnoe-hranenie)

| :speech_balloon: | :cloud: | [PaidStorage()](/src/API/Endpoint/Subpoint/PaidStorage.php) |
| ---------------- | ------- | ----------------------------------------------------------- |
| Создать отчёт    | /api/v1/paid_storage                         | PaidStorage()->**makeReport()**        |
| Проверить статус | /api/v1/paid_storage/tasks/{taskId}/status   | PaidStorage()->**checkReportStatus()** |
| Получить отчёт   | /api/v1/paid_storage/tasks/{taskId}/download | PaidStorage()->**getReport()**         |
<br>

## [WBSeller API](/docs/API.md) / Analytics()->Brands()

```php
$wbSellerAPI = new \Dakword\WBSeller\API($options);
$Brands = $wbSellerAPI->Analytics()->Brands();
```
Wildberries API Аналитика / [**Доля бренда в продажах**](https://openapi.wb.ru/analytics/api/ru/#tag/Dolya-brenda-v-prodazhah)

| :speech_balloon: | :cloud: | [Brands()](/src/API/Endpoint/Subpoint/Brands.php) |
| ---------------- | ------- | ------------------------------------------------- |
| Бренды продавца                 | /api/v1/analytics/brand-share/brands          | Brands()->**getBrands()**              |
| Родительские категории бренда   | /api/v1/analytics/brand-share/parent-subjects | Brands()->**getBrandParentSubjects()** |
| Отчёт по доле бренда в продажах | /api/v1/analytics/brand-share                 | Brands()->**getReport()**              |
<br>

## [WBSeller API](/docs/API.md) / Analytics()->WarehouseRemains()

```php
$wbSellerAPI = new \Dakword\WBSeller\API($options);
$WarehouseRemains = $wbSellerAPI->Analytics()->WarehouseRemains();
```
Wildberries API Аналитика / [**Остатки на складах**](https://openapi.wb.ru/analytics/api/ru/#tag/Otchyot-po-ostatkam-na-skladah)

| :speech_balloon: | :cloud: | [WarehouseRemains()](/src/API/Endpoint/Subpoint/WarehouseRemains.php) |
| ---------------- | ------- | --------------------------------------------------------------------- |
| Создать отчёт    | /api/v1/warehouse_remains                         | WarehouseRemains()->**makeReport()**        |
| Проверить статус | /api/v1/warehouse_remains/tasks/{taskId}/status   | WarehouseRemains()->**checkReportStatus()** |
| Получить отчёт   | /api/v1/warehouse_remains/tasks/{taskId}/download | WarehouseRemains()->**getReport()**         |
<br>

## [WBSeller API](/docs/API.md) / Analytics()->BannedProducts()

```php
$wbSellerAPI = new \Dakword\WBSeller\API($options);
$BannedProducts = $wbSellerAPI->Analytics()->BannedProducts();
```
Wildberries API Аналитика / [**Скрытые товары**](https://openapi.wildberries.ru/analytics/api/ru/#tag/Skrytye-tovary)

| :speech_balloon: | :cloud: | [BannedProducts()](/src/API/Endpoint/Subpoint/BannedProducts.php) |
| ---------------- | ------- | ----------------------------------------------------------------- |
| Заблокированные карточки | /api/v1/analytics/banned-products/blocked  | BannedProducts()->**blocked()**  |
| Скрытые из каталога      | /api/v1/analytics/banned-products/shadowed | BannedProducts()->**shadowed()** |
