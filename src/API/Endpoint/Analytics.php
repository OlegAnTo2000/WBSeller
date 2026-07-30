<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint;

use Dakword\WBSeller\API\Response\ApiResponse;

use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\API\Endpoint\Subpoint\BannedProducts;
use Dakword\WBSeller\API\Endpoint\Subpoint\Brands;
use Dakword\WBSeller\API\Endpoint\Subpoint\PaidStorage;
use Dakword\WBSeller\API\Endpoint\Subpoint\WarehouseRemains;
use DateTime;
use InvalidArgumentException;

class Analytics extends AbstractEndpoint
{
    private const SALES_FUNNEL_ORDER_FIELDS = [
        'openCard',
        'addToCart',
        'orderCount',
        'orderSum',
        'buyoutCount',
        'buyoutSum',
        'cancelCount',
        'cancelSum',
        'avgPrice',
        'stockMpQty',
        'stockWbQty',
        'shareOrderPercent',
        'addToWishlist',
        'timeToReady',
        'localizationPercent',
        'wbClub.orderCount',
        'wbClub.orderSum',
        'wbClub.buyoutSum',
        'wbClub.cancelSum',
        'wbClub.buyoutCount',
        'wbClub.avgPrice',
        'wbClub.buyoutPercent',
        'wbClub.avgOrderCountPerDay',
        'wbClub.cancelCount',
    ];

    private const CSV_REPORT_TYPES = [
        'DETAIL_HISTORY_REPORT',
        'GROUPED_HISTORY_REPORT',
        'SEARCH_QUERIES_PREMIUM_REPORT_GROUP',
        'SEARCH_QUERIES_PREMIUM_REPORT_PRODUCT',
        'SEARCH_QUERIES_PREMIUM_REPORT_TEXT',
        'STOCK_HISTORY_REPORT_CSV',
        'STOCK_HISTORY_DAILY_CSV',
    ];

    protected string $apiName = 'analytics';

    /**
     * Скрытые товары
     *
     * @deprecated Методы раздела отсутствуют в актуальной схеме Analytics.
     * @return BannedProducts
     */
    public function BannedProducts(): BannedProducts
    {
        return new BannedProducts($this);
    }
    /**
     * Доля бренда в продажах
     *
     * @deprecated Методы раздела отсутствуют в актуальной схеме Analytics.
     * @return Brands
     */
    public function Brands(): Brands
    {
        return new Brands($this);
    }

    /**
     * Платное хранение
     *
     * @deprecated Методы раздела отсутствуют в актуальной схеме Analytics.
     * @return PaidStorage
     */
    public function PaidStorage(): PaidStorage
    {
        return new PaidStorage($this);
    }

    /**
     * Отчёт по остаткам на складах
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Otchyot-po-ostatkam-na-skladah
     *
     * @deprecated Методы раздела отсутствуют в актуальной схеме Analytics.
     * @return WarehouseRemains
     */
    public function WarehouseRemains(): WarehouseRemains
    {
        return new WarehouseRemains($this);
    }

    /*
     * ВОРОНКА ПРОДАЖ
     * --------------------------------------------------------------------------
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Voronka-prodazh
     */

    /**
     * Получение статистики КТ за выбранный период,
     * по nmID/предметам/брендам/тегам
     *
     * Поля brandNames,objectIDs, tagIDs, nmIDs могут быть пустыми, тогда в ответе идут все карточки продавца.
     * При выборе нескольких полей в ответ приходят данные по карточкам, у которых есть все выбранные поля.
     * Можно получить отчёт максимум за последний год (365 дней).
     * Максимум 3 запроса в минуту.
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Voronka-prodazh/paths/~1api~1v2~1nm-report~1detail/post
     *
     * @param DateTime $dateFrom  Начало периода
     * @param DateTime $dateTo    Конец периода
     * @param array    $filter    Фильтр по параметрам [
     *                                'brandNames' => [string, string, ...],
     *                                'objectIDs' => [int, int, ...],
     *                                'tagIDs' => [int, int, ...],
     *                                'nmIDs' => [int, int, ...],
     *                            ]
     * @param int      $page      Ноомер страницы
     * @param string   $timezone  Временная зона. Если не указано, то по умолчанию используется Europe/Moscow.
     * @param string   $orderBy   Вид сортировки: openCard - по открытию карточки (переход на страницу товара)
     *                                            addToCart - по добавлениям в корзину
     *                                            orders - по кол-ву заказов
     *                                            avgRubPrice - по средней цене в рублях
     *                                            ordersSumRub - по сумме заказов в рублях
     *                                            stockMpQty - по кол-ву остатков маркетплейса шт.
     *                                            stockWbQty - по кол-ву остатков на складе шт.
     * @param string   $direction Направление сортировки (asc - по возрастанию, desc - по убыванию)
     *
     * @return ApiResponse
     *      data: {
     *          page: integer, isNextPage: bool,
     *          cards: [objectj, object, ...]
     *      },
     *      error: bool, errorText: string, additionalErrors: [object, object, ...]
     * }
     *
     * @throws InvalidArgumentException Неизвестный вид сортировки
     * @throws InvalidArgumentException Неизвестный порядок сортировки
     * @deprecated Используйте v3SalesFunnelProducts().
     */
    public function nmReportDetail(DateTime $dateFrom, DateTime $dateTo, array $filter = [], int $page = 1, string $timezone='Europe/Moscow', string $orderBy = 'openCard', string $direction = 'desc'): ApiResponse {
        if (!in_array($orderBy, ["openCard", "addToCart", "orders", "avgRubPrice", "ordersSumRub", "stockMpQty", "stockWbQty", "cancelSumRub", "cancelCount", "buyoutCount", "buyoutSumRub"])) {
            throw new InvalidArgumentException('Неизвестный вид сортировки: ' . $orderBy);
        }
        if (!in_array($direction, ["asc", "desc"])) {
            throw new InvalidArgumentException('Неизвестный порядок сортировки: ' . $direction);
        }
        return $this->postRequest('/api/v2/nm-report/detail', [
            'nmIDs' => $this->getFromFilter('nmIDs', $filter),
            'brandNames' => $this->getFromFilter('brandNames', $filter),
            'objectIDs' => $this->getFromFilter('objectIDs', $filter),
            'tagIDs' => $this->getFromFilter('tagIDs', $filter),
            'period' => [
                'begin' => $dateFrom->format('Y-m-d H:i:s'),
                'end' => $dateTo->format('Y-m-d H:i:s'),
            ],
            'timezone' => $timezone,
            'page' => $page,
            'orderBy' => [
                'field' => $orderBy,
                'mode' => $direction,
            ],
        ]);
    }

    /**
     * Получение статистики КТ за период,
     * сгруппированный по предметам, брендам и тегам
     *
     * Поля brandNames, objectIDs, tagIDs могут быть пустыми,
     * тогда группировка происходит по всем карточкам продавца.
     * Можно получить отчёт максимум за последний год (365 дней).
     * Максимум 3 запроса в минуту.
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Voronka-prodazh/paths/~1api~1v2~1nm-report~1grouped/post
     *
     * @param DateTime $dateFrom  Начало периода
     * @param DateTime $dateTo    Конец периода
     * @param array    $filter    Фильтр по параметрам [
     *                                'brandNames' => [string, string, ...],
     *                                'objectIDs' => [int, int, ...],
     *                                'tagIDs' => [int, int, ...],
     *                            ]
     * @param int      $page      Ноомер страницы
     * @param string   $timezone  Временная зона. Если не указано, то по умолчанию используется Europe/Moscow.
     * @param string   $orderBy   Вид сортировки: openCard - по открытию карточки (переход на страницу товара)
     *                                            addToCart - по добавлениям в корзину
     *                                            orders - по кол-ву заказов
     *                                            avgRubPrice - по средней цене в рублях
     *                                            ordersSumRub - по сумме заказов в рублях
     *                                            stockMpQty - по кол-ву остатков маркетплейса шт.
     *                                            stockWbQty - по кол-ву остатков на складе шт.
     * @param string   $direction Направление сортировки (asc - по возрастанию, desc - по убыванию)
     *
     * @return ApiResponse
     *      data: {
     *          page: integer, isNextPage: bool,
     *          groups: [object, object, ...]
     *      },
     *      error: bool, errorText: string, additionalErrors: [object, object, ...]
     * }
     *
     * @throws InvalidArgumentException Неизвестный вид сортировки
     * @throws InvalidArgumentException Неизвестный порядок сортировки
     * @deprecated Отсутствует в актуальной схеме Analytics.
     */
    public function nmReportGrouped(DateTime $dateFrom, DateTime $dateTo, array $filter = [], int $page = 1, string $timezone = 'Europe/Moscow', string $orderBy = 'openCard', string $direction = 'desc'): ApiResponse {
        if (!in_array($orderBy, ["openCard", "addToCart", "orders", "avgRubPrice", "ordersSumRub", "stockMpQty", "stockWbQty"])) {
            throw new InvalidArgumentException('Неизвестный вид сортировки: ' . $orderBy);
        }
        if (!in_array($direction, ["asc", "desc"])) {
            throw new InvalidArgumentException('Неизвестный порядок сортировки: ' . $direction);
        }
        return $this->postRequest('/api/v2/nm-report/grouped', [
            'brandNames' => $this->getFromFilter('brandNames', $filter),
            'objectIDs' => $this->getFromFilter('objectIDs', $filter),
            'tagIDs' => $this->getFromFilter('tagIDs', $filter),
            'period' => [
                'begin' => $dateFrom->format('Y-m-d H:i:s'),
                'end' => $dateTo->format('Y-m-d H:i:s'),
            ],
            'timezone' => $timezone,
            'page' => $page,
            'orderBy' => [
                'field' => $orderBy,
                'mode' => $direction,
            ],
        ]);
    }

    /**
     * Получение статистики КТ по дням/неделям по выбранным nmID
     *
     * Можно получить отчёт максимум за последнюю неделю.
     * Максимум 3 запроса в минуту.
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Voronka-prodazh/paths/~1api~1v2~1nm-report~1detail~1history/post
     *
     * @param array    $nmIDs      Артикулы Wildberries (максимум 20)
     * @param DateTime $dateFrom   Начало периода
     * @param DateTime $dateTo     Конец периода
     * @param string   $agregation Тип аггрегации: day, week
     * @param string   $timezone   Временная зона
     *
     * @return ApiResponse
     *      data: [object, object, ...],
     *      error: bool, errorText: string, additionalErrors: [object, object, ...]
     * }
     *
     * @throws InvalidArgumentException Превышение максимального количества переданных артикулов
     * @throws InvalidArgumentException Неизвестный тип агрегации
     * @deprecated Используйте v3SalesFunnelProductsHistory().
     */
    public function nmReportDetailHistory(array $nmIDs, DateTime $dateFrom, DateTime $dateTo, string $agregation = 'day', string $timezone = 'Europe/Moscow'): ApiResponse {
        $maxCount = 20;
        if (count($nmIDs) > $maxCount) {
            throw new InvalidArgumentException("Превышение максимального количества переданных артикулов: {$maxCount}");
        }
        if (!in_array($agregation, ["day", "week"])) {
            throw new InvalidArgumentException('Неизвестный тип агрегации: ' . $agregation);
        }
        return $this->postRequest('/api/v2/nm-report/detail/history', [
            'nmIDs' => $nmIDs,
            'period' => [
                'begin' => $dateFrom->format('Y-m-d'),
                'end' => $dateTo->format('Y-m-d'),
            ],
            'timezone' => $timezone,
            'aggregationLevel' => $agregation,
        ]);
    }

    /**
     * Статистика карточек товаров за период
     * 
     * Можно получить отчёт максимум за последние 365 дней.
     * 3 запроса в минуту.
     * Клиентский limit по умолчанию оставлен 1000 для обратной совместимости;
     * серверное значение по умолчанию в Swagger — 50, максимум — 1000.
     * 
     * @link https://dev.wildberries.ru/openapi/analytics/#tag/Voronka-prodazh/operation/postSalesFunnelProducts
     */
    public function v3SalesFunnelProducts(
        DateTime $selectedPeriodFrom,
        DateTime $selectedPeriodTo,
        ?DateTime $pastPeriodFrom = null,
        ?DateTime $pastPeriodTo = null,
        array $nmIds = [],
        array $brandNames = [],
        array $subjectIds = [],
        array $tagIds = [],
        bool $skipDeletedNm = false,
        string $orderByField = 'openCard',
        string $orderByMode = 'desc',
        int $limit = 1000,
        int $offset = 0
    ): ApiResponse {
        if (($pastPeriodFrom === null) !== ($pastPeriodTo === null)) {
            throw new InvalidArgumentException('Для сравнения нужно передать обе границы прошлого периода');
        }
        if (count($nmIds) > 1000) {
            throw new InvalidArgumentException('Превышено максимальное количество артикулов WB: 1000');
        }
        $this->validateOrderBy($orderByField, $orderByMode, self::SALES_FUNNEL_ORDER_FIELDS);
        $this->validatePagination($limit, $offset);

        $params = [
            'selectedPeriod' => [
                'start' => $selectedPeriodFrom->format('Y-m-d'),
                'end' => $selectedPeriodTo->format('Y-m-d'),
            ],
            'nmIds'      => $nmIds,
            'subjectIds' => $subjectIds,
            'tagIds'     => $tagIds,
            'brandNames' => $brandNames,
            'skipDeletedNm' => $skipDeletedNm,
            'orderBy' => [
                'field' => $orderByField,
                'mode' => $orderByMode,
            ],
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($pastPeriodFrom !== null && $pastPeriodTo !== null) {
            $params['pastPeriod'] = [
                'start' => $pastPeriodFrom->format('Y-m-d'),
                'end' => $pastPeriodTo->format('Y-m-d'),
            ];
        }

        return $this->postRequest('/api/analytics/v3/sales-funnel/products', $params);
    }

    /**
     * История статистики карточек товаров.
     *
     * @param array<int, int> $nmIds Артикулы WB, от 1 до 20.
     */
    public function v3SalesFunnelProductsHistory(
        DateTime $selectedPeriodFrom,
        DateTime $selectedPeriodTo,
        array $nmIds,
        bool $skipDeletedNm = false,
        string $aggregationLevel = 'day'
    ): ApiResponse {
        if ($nmIds === [] || count($nmIds) > 20) {
            throw new InvalidArgumentException('Количество артикулов WB должно быть от 1 до 20');
        }
        $this->validateAggregationLevel($aggregationLevel);

        return $this->postRequest('/api/analytics/v3/sales-funnel/products/history', [
            'selectedPeriod' => [
                'start' => $selectedPeriodFrom->format('Y-m-d'),
                'end' => $selectedPeriodTo->format('Y-m-d'),
            ],
            'nmIds' => $nmIds,
            'skipDeletedNm' => $skipDeletedNm,
            'aggregationLevel' => $aggregationLevel,
        ]);
    }

    /**
     * История воронки продаж с группировкой по предметам, брендам и ярлыкам.
     */
    public function v3SalesFunnelGroupedHistory(
        DateTime $selectedPeriodFrom,
        DateTime $selectedPeriodTo,
        array $brandNames = [],
        array $subjectIds = [],
        array $tagIds = [],
        bool $skipDeletedNm = false,
        string $aggregationLevel = 'day'
    ): ApiResponse {
        $this->validateAggregationLevel($aggregationLevel);

        return $this->postRequest('/api/analytics/v3/sales-funnel/grouped/history', [
            'selectedPeriod' => [
                'start' => $selectedPeriodFrom->format('Y-m-d'),
                'end' => $selectedPeriodTo->format('Y-m-d'),
            ],
            'brandNames' => $brandNames,
            'subjectIds' => $subjectIds,
            'tagIds' => $tagIds,
            'skipDeletedNm' => $skipDeletedNm,
            'aggregationLevel' => $aggregationLevel,
        ]);
    }

    /**
     * Создать CSV-отчёт с расширенной аналитикой.
     *
     * Состав `$params` зависит от `$reportType` и описан в 11-analytics.yaml.
     */
    public function createAnalyticsReport(
        string $id,
        string $reportType,
        array $params,
        ?string $userReportName = null
    ): ApiResponse {
        if (!in_array($reportType, self::CSV_REPORT_TYPES, true)) {
            throw new InvalidArgumentException('Неизвестный тип CSV-отчёта: ' . $reportType);
        }

        $body = [
            'id' => $id,
            'reportType' => $reportType,
            'params' => $params,
        ];
        if ($userReportName !== null) {
            $body['userReportName'] = $userReportName;
        }

        return $this->postRequest('/api/v2/nm-report/downloads', $body);
    }

    /** @param array<int, string> $downloadIds */
    public function getAnalyticsReports(array $downloadIds = []): ApiResponse
    {
        $query = $downloadIds === [] ? [] : ['filter[downloadIds]' => $downloadIds];

        return $this->getRequest('/api/v2/nm-report/downloads', $query);
    }

    public function retryAnalyticsReport(string $downloadId): ApiResponse
    {
        return $this->postRequest('/api/v2/nm-report/downloads/retry', ['downloadId' => $downloadId]);
    }

    public function downloadAnalyticsReportFile(string $downloadId): ApiResponse
    {
        return $this->getRequest('/api/v2/nm-report/downloads/file/' . rawurlencode($downloadId));
    }

    /**
     * Главная страница отчёта по поисковым запросам.
     *
     * Обязательные поля: currentPeriod, orderBy, positionCluster, limit, offset.
     * Опциональные: pastPeriod, nmIds, subjectIds, brandNames, tagIds,
     * includeSubstitutedSKUs, includeSearchTexts.
     */
    public function searchReport(array $params): ApiResponse
    {
        return $this->postRequest('/api/v2/search-report/report', $params);
    }

    /**
     * Обязательные поля: currentPeriod, orderBy, positionCluster, limit, offset.
     * Опциональные: pastPeriod, nmIds, subjectIds, brandNames, tagIds,
     * includeSubstitutedSKUs, includeSearchTexts.
     */
    public function searchReportGroups(array $params): ApiResponse
    {
        return $this->postRequest('/api/v2/search-report/table/groups', $params);
    }

    /**
     * Обязательные поля: currentPeriod, orderBy, positionCluster, limit, offset.
     * Опциональные: pastPeriod, subjectId, brandName, tagId, nmIds,
     * includeSubstitutedSKUs, includeSearchTexts.
     */
    public function searchReportDetails(array $params): ApiResponse
    {
        return $this->postRequest('/api/v2/search-report/table/details', $params);
    }

    /**
     * Обязательные поля: currentPeriod, nmIds, limit, topOrderBy, orderBy.
     * Опциональные: pastPeriod, includeSubstitutedSKUs, includeSearchTexts.
     */
    public function searchReportProductSearchTexts(array $params): ApiResponse
    {
        return $this->postRequest('/api/v2/search-report/product/search-texts', $params);
    }

    /** Обязательные поля: period, nmId, searchTexts. */
    public function searchReportProductOrders(array $params): ApiResponse
    {
        return $this->postRequest('/api/v2/search-report/product/orders', $params);
    }

    /** Опциональные поля: nmIds, chrtIds, limit, offset. */
    public function stocksReportWbWarehouses(array $params = []): ApiResponse
    {
        return $this->postRequest('/api/analytics/v1/stocks-report/wb-warehouses', $params);
    }

    /**
     * Обязательные поля: availabilityFilters, currentPeriod, stockType, skipDeletedNm, orderBy, offset.
     * Опциональные: nmIDs, subjectIDs, brandNames, tagIDs, limit.
     */
    public function stocksReportProductGroups(array $params): ApiResponse
    {
        return $this->postRequest('/api/v2/stocks-report/products/groups', $params);
    }

    /**
     * Обязательные поля: currentPeriod, stockType, skipDeletedNm, orderBy, availabilityFilters, offset.
     * Опциональные: nmIDs, subjectID, brandName, tagID, limit.
     */
    public function stocksReportProducts(array $params): ApiResponse
    {
        return $this->postRequest('/api/v2/stocks-report/products/products', $params);
    }

    /** Обязательные поля: nmID, currentPeriod, stockType, orderBy, includeOffice. */
    public function stocksReportProductSizes(array $params): ApiResponse
    {
        return $this->postRequest('/api/v2/stocks-report/products/sizes', $params);
    }

    /**
     * Обязательные поля: currentPeriod, stockType, skipDeletedNm.
     * Опциональные: nmIDs, subjectIDs, brandNames, tagIDs.
     */
    public function stocksReportOffices(array $params): ApiResponse
    {
        return $this->postRequest('/api/v2/stocks-report/offices', $params);
    }

    /**
     * Обязательные поля: currentPeriod, orderBy, offset.
     * Опциональные: pastPeriod, nmIds, subjectIds, brandNames, tagIds,
     * isNotIncludeNmsWithoutSales, onlyShadowedNms, limit.
     */
    public function itemRating(array $params): ApiResponse
    {
        return $this->postRequest('/api/analytics/v2/item-rating', $params);
    }

    /**
     * Обязательные поля: currentPeriod, orderBy, offset.
     * Опциональные: pastPeriod, nmIds, subjectIds, brandNames, tagIds,
     * isNotIncludeNMsWithoutSales, limit.
     *
     * @deprecated Используйте itemRating().
     */
    public function itemRatingV1(array $params): ApiResponse
    {
        return $this->postRequest('/api/analytics/v1/item-rating', $params);
    }

    /**
     * Получение статистики КТ по дням за период,
     * сгруппированный по предметам, брендам и тегам
     *
     * Параметры фильтра brandNames, objectIDs, tagIDs могут быть пустыми,
     * тогда группировка происходит по всем карточкам продавца.
     * В запросе произведение количества предметов, брендов, тегов не должно быть больше 16.
     * Можно получить отчёт максимум за последнюю неделю.
     * Максимум 3 запроса в минуту.
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Voronka-prodazh/paths/~1api~1v2~1nm-report~1grouped~1history/post
     *
     * @param DateTime $dateFrom   Начало периода
     * @param DateTime $dateTo     Конец периода
     * @param array    $filter     Фильтр по параметрам [
     *                                 'brandNames' => [string, string, ...],
     *                                 'objectIDs' => [int, int, ...],
     *                                 'tagIDs' => [int, int, ...],
     *                             ]
     * @param string   $agregation Тип аггрегации: day, week
     * @param string   $timezone   Временная зона
     *
     * @return ApiResponse
     *      data: [object, object, ...],
     *      error: bool, errorText: string, additionalErrors: [object, object, ...]
     * }
     *
     * @throws InvalidArgumentException Превышение максимального произведения количества предметов, брендов, тегов
     * @throws InvalidArgumentException Неизвестный тип агрегации
     * @deprecated Используйте v3SalesFunnelGroupedHistory().
     */
    public function nmReportGroupedHistory(DateTime $dateFrom, DateTime $dateTo, array $filter = [], string $agregation = 'day', string $timezone = 'Europe/Moscow'): ApiResponse {
        $max = 16;
        if (
            count($this->getFromFilter('objectIDs', $filter))
          * count($this->getFromFilter('brandNames', $filter))
          * count($this->getFromFilter('tagIDs', $filter)) > $max
        ) {
            throw new InvalidArgumentException("Превышение максимального произведения количества предметов, брендов, тегов: {$max}");
        }
        if (!in_array($agregation, ["day", "week"])) {
            throw new InvalidArgumentException('Неизвестный тип агрегации: ' . $agregation);
        }
        return $this->postRequest('/api/v2/nm-report/grouped/history', [
            'brandNames' => $this->getFromFilter('brandNames', $filter),
            'objectIDs' => $this->getFromFilter('objectIDs', $filter),
            'tagIDs' => $this->getFromFilter('tagIDs', $filter),
            'period' => [
                'begin' => $dateFrom->format('Y-m-d'),
                'end' => $dateTo->format('Y-m-d'),
            ],
            'timezone' => $timezone,
            'aggregationLevel' => $agregation,
        ]);
    }

    /*
     * ТОВАРЫ С ОБЯЗАТЕЛЬНОЙ МАРКИРОВКОЙ
     * --------------------------------------------------------------------------
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Tovary-s-obyazatelnoj-markirovkoj
     */

    /**
     * Отчёт по товарам с обязательной маркировкой
     *
     * Возвращает операции по маркируемым товарам.
     * Максимум 10 запросов за 5 часов.
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Tovary-s-obyazatelnoj-markirovkoj/paths/~1api~1v1~1analytics~1excise-report/post
     * @deprecated Отсутствует в актуальной схеме Analytics.
     *
     * @param DateTime $dateFrom  Дата начала отчётного периода
     * @param DateTime $dateTo    Дата окончания отчётного периода
     * @param array    $countries Код стран по стандарту ISO 3166-2.
     *                            "AM", "BY", "KG", "KZ", "RU", "UZ"
     *                            Чтобы получить данные по всем странам, оставьте параметр пустым
     *
     * @return ApiResponse
     */
    public function exciseReport(DateTime $dateFrom, DateTime $dateTo, array $countries = []): ApiResponse {
        return $this->postRequest('/api/v1/analytics/excise-report?dateFrom=' . $dateFrom->format('Y-m-d') . '&dateTo=' . $dateTo->format('Y-m-d'), [
            'countries' => $countries,
        ]);
    }

    /*
     * ПЛАТНАЯ ПРИЕМКА
     * --------------------------------------------------------------------------
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Platnaya-priyomka
     */

    /**
     * Отчет о платной приемке
     *
     * Возвращает даты и стоимость приёмки. Можно получить отчёт максимум за 31 день.
     * Максимум 1 запрос в минуту
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Platnaya-priyomka/paths/~1api~1v1~1analytics~1acceptance-report/get
     * @deprecated Отсутствует в актуальной схеме Analytics.
     *
     * @param DateTime $dateFrom Начало отчётного периода
     * @param DateTime $dateTo   Конец отчётного периода
     *
     * @return ApiResponse
     */
    public function acceptanceReport(DateTime $dateFrom, DateTime $dateTo): ApiResponse {
        return $this->getRequest('/api/v1/analytics/acceptance-report', [
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
        ]);
    }

     /*
     * ОТЧЕТЫ ПО УДЕРЖАНИЯМ
     * --------------------------------------------------------------------------
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Otchyoty-po-uderzhaniyam
     */

    /**
     * Отчет по удержаниям за самовыкупы
     *
     * Отчёт формируется каждую неделю по средам, до 7:00 по московскому времени, и содержит данные за одну неделю.
     * Также можно получить отчёт за всё время с августа 2023, для этого не передавайте параметр $date.
     * Максимум 10 запросов за 100 минут.
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Otchyoty-po-uderzhaniyam/paths/~1api~1v1~1analytics~1antifraud-details/get
     * @deprecated Отсутствует в актуальной схеме Analytics.
     *
     * @param DateTime|null $date Дата, которая входит в отчётный период
     *
     * @return ApiResponse
     */
    public function antifraudDetails(?DateTime $date = null): ApiResponse {
        return $this->getRequest('/api/v1/analytics/antifraud-details', $date ? ['date' => $date->format('Y-m-d')] : []);
    }

    /**
     * Отчет об удержаниях за подмену товара
     *
     * Возвращает отчёт об удержаниях за отправку не тех товаров, пустых коробок или коробок без товара,
     * но с посторонними предметами. В таких случаях удерживается 100% от стоимости заказа.
     * Можно получить отчёт максимум за 31 день, доступны данные с июня 2023.
     * Максимум 1 запрос в минуту.
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Otchyoty-po-uderzhaniyam/paths/~1api~1v1~1analytics~1incorrect-attachments/get
     * @deprecated Отсутствует в актуальной схеме Analytics.
     *
     * @param DateTime $dateFrom Начало отчётного периода
     * @param DateTime $dateTo   Конец отчётного периода
     *
     * @return ApiResponse
     */
    public function incorrectAttachments(DateTime $dateFrom, DateTime $dateTo): ApiResponse {
        return $this->getRequest('/api/v1/analytics/incorrect-attachments', [
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
        ]);
    }

    /**
     * Коэффициент логистики и хранения
     *
     * Возвращает коэффициенты логистики и хранения.
     * Они рассчитываются на неделю (с понедельника по воскресенье).
     * Можно получить данные с 31.10.2022.
     * Максимум 1 запрос в минуту.
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Otchyoty-po-uderzhaniyam/paths/~1api~1v1~1analytics~1storage-coefficient/get
     * @deprecated Отсутствует в актуальной схеме Analytics.
     *
     * @param DateTime|null $date Дата, которая входит в отчётный период
     *
     * @return ApiResponse
     */
    public function storageCoefficient(?DateTime $date = null): ApiResponse {
        return $this->getRequest('/api/v1/analytics/storage-coefficient', $date ? ['date' => $date->format('Y-m-d')] : []);
    }

    /**
     * Отчёт о штрафах за отсутствие обязательной маркировки товаров
     *
     * В отчёте представлены фотографии товаров, на которых маркировка отсутствует
     * либо не считывается.
     * Можно получить данные максимум за 31 день, начиная с марта 2024.
     * Максимум 10 запросов за 10 минут
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Otchyoty-po-uderzhaniyam/paths/~1api~1v1~1analytics~1goods-labeling/get
     * @deprecated Отсутствует в актуальной схеме Analytics.
     *
     * @param DateTime $dateFrom Начало отчётного периода
     * @param DateTime $dateTo   Конец отчётного периода
     *
     * @return ApiResponse
     */
    public function goodsLabeling(DateTime $dateFrom, DateTime $dateTo): ApiResponse {
        return $this->getRequest('/api/v1/analytics/goods-labeling', [
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
        ]);
    }

    /**
     * Отчёт об удержаниях за смену характеристик товара
     *
     * Если товары после приёмки не соответствуют заявленным цветам и размерам,
     * и на складе их перемаркировали с правильными характеристиками,
     * по таким товарам назначается штраф.
     * Можно получить отчёт максимум за 31 день, доступны данные с 28 декабря 2021.
     * Максимум 10 запросов за 10 минут
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Otchyoty-po-uderzhaniyam/paths/~1api~1v1~1analytics~1characteristics-change/get
     * @deprecated Отсутствует в актуальной схеме Analytics.
     *
     * @param DateTime $dateFrom Начало отчётного периода
     * @param DateTime $dateTo   Конец отчётного периода
     *
     * @return ApiResponse
     */
    public function characteristicsChange(DateTime $dateFrom, DateTime $dateTo): ApiResponse {
        return $this->getRequest('/api/v1/analytics/characteristics-change', [
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
        ]);
    }

    /*
     * ПРОДАЖИ ПО РЕГИОНАМ
     * --------------------------------------------------------------------------
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Prodazhi-po-regionam
     */

    /**
     * Отчет о продажах сгруппированный по регионам стран
     *
     * Возвращает данные продаж, сгруппированные по регионам стран.
     * Можно получить отчёт максимум за 31 день.
     * Максимум 1 запрос в 10 секунд
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Prodazhi-po-regionam/paths/~1api~1v1~1analytics~1region-sale/get
     * @deprecated Отсутствует в актуальной схеме Analytics.
     *
     * @param DateTime $dateFrom Начало отчётного периода
     * @param DateTime $dateTo   Конец отчётного периода
     *
     * @return ApiResponse
     */
    public function regionSale(DateTime $dateFrom, DateTime $dateTo): ApiResponse {
        return $this->getRequest('/api/v1/analytics/region-sale', [
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
        ]);
    }

    /**
     * @param array<int, string> $allowedFields
     */
    private function validateOrderBy(string $field, string $mode, array $allowedFields): void
    {
        if (!in_array($field, $allowedFields, true)) {
            throw new InvalidArgumentException('Неизвестное поле сортировки: ' . $field);
        }
        if (!in_array($mode, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Неизвестный порядок сортировки: ' . $mode);
        }
    }

    private function validatePagination(int $limit, int $offset): void
    {
        if ($limit < 0 || $limit > 1000) {
            throw new InvalidArgumentException('Значение limit должно быть от 0 до 1000');
        }
        if ($offset < 0) {
            throw new InvalidArgumentException('Значение offset не может быть отрицательным');
        }
    }

    private function validateAggregationLevel(string $aggregationLevel): void
    {
        if (!in_array($aggregationLevel, ['day', 'week'], true)) {
            throw new InvalidArgumentException('Неизвестный тип агрегации: ' . $aggregationLevel);
        }
    }

    private function getFromFilter(string $param, array $filter)
    {
        $key = strtolower($param);
        $modifKeys = array_change_key_case($filter);
        return (array_key_exists($key, $modifKeys) ? $modifKeys[$key] : []);
    }

    /*
     * ОТЧЕТ ПО ВОЗВРАТАМ ТОВАРОВ
     * --------------------------------------------------------------------------
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Otchyot-po-vozvratam-tovarov
     */

    /**
     * Получить отчёт по возвратам товаров
     *
     * Возвращает перечень возвратов товаров продавцу.
     * Одним запросом можно получить отчёт максимум за 31 день.
     * Максимум 1 запрос в минуту
     * @link https://openapi.wb.ru/analytics/api/ru/#tag/Otchyot-po-vozvratam-tovarov/paths/~1api~1v1~1analytics~1goods-return/get
     * @deprecated Отсутствует в актуальной схеме Analytics.
     *
     * @param DateTime $dateFrom Начало отчётного периода
     * @param DateTime $dateTo   Конец отчётного периода
     *
     * @return ApiResponse
     */
    public function goodsReturn(DateTime $dateFrom, DateTime $dateTo): ApiResponse {
        return $this->getRequest('/api/v1/analytics/goods-return', [
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
        ]);
    }

    /*
     * Динамика оборачиваемости
     * --------------------------------------------------------------------------
     */

    /**
     * Ежедневная динамика
     *
     * Метод предоставляет данные о ежедневной динамике.
     * Можно получить отчёт максимум за 31 день.
     * Максимум 1 запрос в 10 секунд.
     * @link https://dev.wildberries.ru/ru/openapi/reports/#tag/Dinamika-oborachivaemosti/paths/~1api~1v1~1turnover-dynamics~1daily-dynamics/get
     * @deprecated Отсутствует в актуальной схеме Analytics.
     *
     * @param DateTime $dateFrom Дата начала отчётного периода
     * @param DateTime $dateTo   Дата окончания отчётного периода
     */
    public function dailyDynamics(DateTime $dateFrom, DateTime $dateTo): ApiResponse {
        return $this->getRequest('/api/v1/turnover-dynamics/daily-dynamics', [
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
        ]);
    }

}
