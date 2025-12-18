<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint;

use DateTime;
use InvalidArgumentException;
use Dakword\WBSeller\Enum\AdvertType;
use Dakword\WBSeller\Enum\AdvertStatus;
use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\API\Endpoint\Subpoint\AdvAuto;
use Dakword\WBSeller\API\Endpoint\Subpoint\AdvFinance;
use Dakword\WBSeller\DTOs\AdvV2SeacatSaveAdResponseDTO;
use Dakword\WBSeller\API\Endpoint\Subpoint\AdvSearchCatalog;
use Dakword\WBSeller\API\Endpoint\Subpoint\AdvSearchClusters;

class Adv extends AbstractEndpoint
{
    /**
     * Сервисы для автоматических кампаний
     *
     * @return AdvAuto
     */
    public function Auto(): AdvAuto
    {
        return new AdvAuto($this);
    }

    /**
     * Сервисы для финансов
     *
     * @return AdvFinance
     */
    public function Finances(): AdvFinance
    {
        return new AdvFinance($this);
    }

    /**
     * Сервисы для кампаний в поиске и поиск + каталог
     *
     * @return AdvSearchCatalog
     */
    public function SearchCatalog(): AdvSearchCatalog
    {
        return new AdvSearchCatalog($this);
    }

    /**
     * Сервисы для кластеров фраз в поиске
     *
     * @return AdvSearchClusters
     */
    public function SearchClusters(): AdvSearchClusters
    {
        return new AdvSearchClusters($this);
    }

    /**
     * Сервисы для кампаний Аукцион
     *
     * @return AdvSearchCatalog
     */
    public function Auction(): AdvSearchCatalog
    {
        return new AdvSearchCatalog($this);
    }

    /**
     * Списки кампаний
     *
     * Метод позволяет получать списки кампаний, сгруппированных по типу и статусу,
     * с информацией о дате последнего изменения кампании.
     * Допускается 5 запросов в секунду.
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Prodvizhenie/paths/~1adv~1v1~1promotion~1count/get
     *
     * @return array Данные по кампаниям
     */
    public function advertsList(): array
    {
        return $this->getRequest('/adv/v1/promotion/count')->adverts ?? [];
    }

    /**
     * Информация о кампаниях
     * 
     * Метод возвращает информацию о рекламных кампаниях с единой или ручной ставкой по их статусам, типам оплаты и ID.
     * Допускается 5 запросов в секунду.
     * 
     * Параметры в доке указаны как обязательные, но если не передавать, то будут возвращены все кампании.
     * 
     * @link https://dev.wildberries.ru/openapi/promotion/#tag/Kampanii/paths/~1api~1advert~1v2~1adverts/get
     *
     * @param array $ids ID кампаний, максимум 50
     * @param array $statuses Статусы кампаний
     * -1 — удалена, процесс удаления будет завершён в течение 10 минут
     * 4 — готова к запуску
     * 7 — завершена
     * 8 — отменена
     * 9 — активна
     * 11 — на паузе
     * @param string $paymentType Тип оплаты cpc/cpm
     *
     * @return array
     */
    public function apiAdvertV2Adverts(
        ?array $ids, 
        ?array $statuses, 
        ?string $paymentType
    ) {
        $data = [];
        
        if ($ids) $data['ids']                  = implode(',', $ids);
        if ($statuses) $data['statuses']        = implode(',', $statuses);
        if ($paymentType) $data['payment_type'] = $paymentType;

        return $this->getRequest('/api/advert/v2/adverts', $data);
    }

    /**
     * Минимальные ставки для карточек товаров
     * Метод возвращает минимальные ставки для карточек товаров в копейках по типу оплаты и местам размещения.
     * @link https://dev.wildberries.ru/openapi/promotion/#tag/Sozdanie-kampanij/paths/~1api~1advert~1v1~1bids~1min/post
     *
     * @param int $advertId Идентификатор кампании
     * @param array $nmIds Идентификаторы номенклатур товаров, максимум 100
     * @param string $paymentType Тип оплаты cpc/cpm
     * @param array $placementTypes Места размещения combined/search/recommendation
     *
     * @return array
     */
    public function apiAdvertV1BidsMin(
        int $advertId,
        array $nmIds,
        string $paymentType, 
        array $placementTypes,
    ) {
        if (empty($nmIds)) {
            throw new InvalidArgumentException("Не переданы номенклатуры товаров");
        }
        if (count($nmIds) > 100) {
            throw new InvalidArgumentException("Превышение максимального количества номенклатур в запросе: 100");
        }
        if (!in_array($paymentType, ['cpc', 'cpm'])) {
            throw new InvalidArgumentException("Неизвестный тип оплаты: {$paymentType}");
        }
        return $this->postRequest('/api/advert/v1/bids/min', [
            'advert_id'       => $advertId,
            'nm_ids'          => $nmIds,
            "payment_type"    => $paymentType,
            "placement_types" => $placementTypes,
        ]);
    }

    /**
     * Удаление кампании
     *
     * Метод позволяет удалять кампании в статусе 4 - готова к запуску.
     * Допускается 5 запросов в секунду.
     * После удаления кампания некоторое время будет находиться в -1 статусе.
     * Полное удаление кампании занимает от 3 до 10 минут.
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Prodvizhenie/paths/~1adv~1v0~1delete/get
     *
     * @param int $id ID кампании
     *
     * @return bool
     */
    public function delete(int $id): bool
    {
        $this->getRequest('/adv/v0/delete', [
            'id' => $id,
        ]);
        return $this->responseCode() == 200;
    }

    /**
     * Создать кампанию
     * 
     * Метод создаёт кампанию:
     * - с ручной ставкой для продвижения товаров в поиске и/или рекомендациях
     * - с единой ставкой для продвижения товаров одновременно в поиске и рекомендациях
     * 
     * IMPORTANT: Максимум 5 запросов в минуту
     * IMPORTANT: Места размещения указываются только для кампаний с ручной ставкой
     * 
     * @link https://dev.wildberries.ru/openapi/promotion/#tag/Sozdanie-kampanij/paths/~1adv~1v2~1seacat~1save-ad/post
     * 
     * @param string $name Название кампании
     * @param array $nms Номенклатуры для кампании, максимум 50
     * @param string $bid_type Тип ставки: manual - ручная, unified - единая (по умолчанию: manual)
     * @param string $payment_type Тип оплаты: cpc - за клик, cpm - за показ (по умолчанию: cpm)
     * @param array $placement_types Места размещения: search - поиск, recommendations - рекомендации (только для кампании с ручной ставкой) (по умолчанию: ["search"])
     * 
     * @throws InvalidArgumentException Превышение максимального количества номенклатур в запросе
     */
    public function advV2SeacatSaveAd(
        string $name, 
        array $nms,
        string $bid_type = 'manual', 
        string $payment_type = 'cpm', 
        array $placement_types = ["search"]
    ) {
        if (count($nms) > 50) {
            throw new InvalidArgumentException("Превышение максимального количества номенклатур в запросе: 50");
        }
        if (!in_array($bid_type, ['manual', 'unified'])) {
            throw new InvalidArgumentException("Неизвестный тип ставки: {$bid_type}");
        }
        if (!in_array($payment_type, ['cpc', 'cpm'])) {
            throw new InvalidArgumentException("Неизвестный тип оплаты: {$payment_type}");
        }
        $data = [
            'name'            => $name,
            'nms'             => $nms,
            'bid_type'        => $bid_type,
            'payment_type'    => $payment_type
        ];
        if ($bid_type == 'manual') $data['placement_types'] = $placement_types;

        $response = $this->postRequest('/adv/v2/seacat/save-ad', $data);
        return AdvV2SeacatSaveAdResponseDTO::fromObject($response);
    }

    /**
     * Переименование кампании
     *
     * @param int $advertId Идентификатор РК, у которой меняется название
     * @param string $name     Новое название (максимум 100 символов)
     *
     * @return bool
     */
    public function renameAdvert(int $advertId, string $name): bool
    {
        $this->postRequest('/adv/v0/rename', [
            'advertId' => $advertId,
            'name' => mb_substr($name, 0, 100)
        ]);
        return $this->responseCode() == 200;
    }

    /**
     * Информация о кампаниях
     *
     * @param int    $status    Статус РК
     * @param int    $type      Тип РК
     * @param string $order     Порядок: "create", "change", "id"
     * @param string $direction Направление: "desc", "asc"
     *
     * @return array
     *
     * @throws InvalidArgumentException Неизвестный статус РК
     * @throws InvalidArgumentException Неизвестный тип РК
     * @throws InvalidArgumentException Неизвестный порядок сортировки
     */
    public function advertsInfo(int $status, int $type, string $order = 'change', string $direction = 'desc'): array
    {
        if (!in_array($status, AdvertStatus::all())) {
            throw new InvalidArgumentException('Неизвестный статус РК: ' . $status);
        }
        $this->checkType($type);
        if (!in_array($order, ["create", "change", "id"])) {
            throw new InvalidArgumentException('Неизвестный порядок сортировки: ' . $order);
        }
        return $this->postRequest('/adv/v1/promotion/adverts?' . http_build_query([
            'status' => $status,
            'type' => $type,
            'order' => $order,
            'direction' => $direction,
        ])) ?? [];
    }

    /**
     * Информация о кампаниях по списку их id
     * 
     * @deprecated Будет удален с 2026-02-02
     * @param array $ids Список ID кампаний. Максимум 50
     * @return array
     * @throws InvalidArgumentException Превышение максимального количества запрашиваемых кампаний
     */
    public function advertsInfoByIds(array $ids): array
    {
        $maxCount = 50;
        if (count($ids) > $maxCount) {
            throw new InvalidArgumentException("Превышение максимального количества запрашиваемых кампаний: {$maxCount}");
        }
        return $this->postRequest('/adv/v1/promotion/adverts', $ids) ?? [];
    }

    /**
     * Изменение ставки у кампании
     *
     * Доступно для РК в карточке товара, поиске или рекомендациях.
     *
     * @deprecated Удален, вместо него 
     * 
     * @param int $advertId   Идентификатор РК
     * @param int $type       Тип РК
     * @param int $cpm        Новое значение ставки
     * @param int $param      Параметр, для которого будет внесено изменение
     *                        (является значением subjectId или setId в зависимости от типа РК)
     * @param int $instrument Тип кампании для изменения ставки в Поиск + Каталог (4 - каталог, 6 - поиск)
     *
     * @return bool
     *
     * @throws InvalidArgumentException Недопустимый тип РК
     */
    public function updateCpm(int $advertId, int $type, int $cpm, int $param, int $instrument): bool
    {
        $this->checkType($type, [AdvertType::ON_CARD, AdvertType::ON_SEARCH, AdvertType::ON_HOME_RECOM]);
        $this->postRequest('/adv/v0/cpm', [
            'advertId'   => $advertId,
            'type'       => $type,
            'cpm'        => $cpm,
            'param'      => $param,
            'instrument' => $instrument,
        ]);
        return $this->responseCode() == 200;
    }

    /**
     * Запуск кампании
     *
     * @param int $id
     *
     * @return bool
     */
    public function start(int $id): bool
    {
        $this->getRequest('/adv/v0/start', ['id' => $id]);
        return $this->responseCode() == 200;
    }

    /**
     * Пауза кампании
     *
     * @param int $id
     *
     * @return bool
     */
    public function pause(int $id): bool
    {
        $this->getRequest('/adv/v0/pause', ['id' => $id]);
        return $this->responseCode() == 200;
    }

    /**
     * Завершение кампании
     *
     * @param int $id
     *
     * @return bool
     */
    public function stop(int $id): bool
    {
        $this->getRequest('/adv/v0/stop', ['id' => $id]);
        return $this->responseCode() == 200;
    }

    /**
     * Изменение ставок в кампаниях
     * 
     * Метод меняет ставки карточек товаров по артикулам WB в кампаниях с единой или ручной ставкой.
     * Для кампаний в статусах 4, 9 и 11.
     * 
     * Допускается 5 запросов в секунду.
     * 
     * @param array $bids Массив ставок [{
     *   "advert_id": 1825035,
     *   "nm_bids": [{
     *     "nm_id": 983512347,
     *     "bid": 10000 (в копейках),
     *     "placement": "combined" (место размещения combined/search/recommendations)
     *   }, ...]
     * }, ...]
     * 
     * @link https://dev.wildberries.ru/openapi/promotion/#tag/Upravlenie-kampaniyami/paths/~1api~1advert~1v1~1bids/patch
     */
    public function apiAdvertV1Bids(
        array $bids
    ) {
        if (empty($bids)) {
            throw new InvalidArgumentException("Не переданы ставки");
        }
        if (count($bids) > 50) {
            throw new InvalidArgumentException("Превышение максимального количества ставок в запросе: 50");
        }
        return $this->patchRequest('/api/advert/v1/bids', [
            'bids' => $bids,
        ]);
    }

    /**
     * Изменение списка карточек товаров в кампании с единой ставкой
     * Метод добавляет и удаляет карточки товаров в кампании с единой ставкой.
     * 
     * IMPORTANT: допускается 60 запросов в минуту
     * IMPORTANT: работает только для кампаний с единой ставкой
     * IMPORTANT: Проверки по параметру delete не предусмотрено
     * 
     * @link https://dev.wildberries.ru/openapi/promotion/#tag/Parametry-kampanij/paths/~1adv~1v1~1auto~1updatenm/post
     * @param int $id Идентификатор кампании
     * @param array<int, int> $add Массив номенклатур товаров для добавления
     * @param array<int, int> $delete Массив номенклатур товаров для удаления
     */
    public function advV1AutoUpdateNm(
        int $id,
        array $add,
        array $delete,
    ) {
        return $this->postRequest('/adv/v1/auto/updatenm?id=' . $id, [
            'add'    => $add,
            'delete' => $delete,
        ]);
    }

    /**
     * Список карточек товаров для кампании с единой ставкой
     * Метод формирует список карточек товаров, которые можно добавить в кампанию с единой ставкой.
     * 
     * IMPORTANT: Работает только для кампаний с единой ставкой
     * IMPORTANT: Максимум 1 запрос в секунду
     * 
     * @param int $id Идентификатор кампании
     * @link https://dev.wildberries.ru/openapi/promotion/#tag/Parametry-kampanij/paths/~1adv~1v1~1auto~1getnmtoadd/get
     */
    public function advV1AutoGetNmToAdd(
        int $id,
    ) {
        return $this->getRequest('/adv/v1/auto/getnmtoadd', ['id' => $id]);
    }

    /**
     * Изменение мест размещения в кампаниях с ручной ставкой
     * Метод меняет места размещения в кампаниях с ручной ставкой и моделью оплаты за показы — cpm.
     * 
     * IMPORTANT: работает только для кампаний с ручной ставкой
     * IMPORTANT: работает только для кампаний с моделью оплаты cpm
     * 
     * @link https://dev.wildberries.ru/openapi/promotion/#tag/Upravlenie-kampaniyami/paths/~1adv~1v0~1auction~1placements/put
     * @param array $placements Массив мест размещения, максимум 50 [{
     *   "advert_id": 1825035,
     *   "placements": {
     *     "search": true, (возможна отправка одного или двух значений, второе не поменяется) (true/false)
     *     "recommendations": true (возможна отправка одного или двух значений, второе не поменяется) (true/false)
     *   }
     * }, ...]
     * 
     * @return bool
     * 
     * @throws InvalidArgumentException Не переданы места размещения
     * @throws InvalidArgumentException Превышение максимального количества мест размещения в запросе
     */
    public function advV0AuctionPlacements(
        array $placements
    ) {
        if (empty($placements)) {
            throw new InvalidArgumentException("Не переданы места размещения");
        }
        if (count($placements) > 50) {
            throw new InvalidArgumentException("Превышение максимального количества мест размещения в запросе: 50");
        }
        $this->putRequest('/adv/v0/auction/placements', [
            'placements' => $placements,
        ]);
        return $this->responseCode() == 204;
    }

    /**
     * Статистика кампаний
     *
     * Максимум 1 запрос в минуту.
     * Данные вернутся для кампаний в статусе 7, 9 и 11.
     * Важно. В запросе можно передавать либо параметр dates либо параметр interval, но не оба.
     * Можно отправить запрос только с ID кампании.
     * При этом вернутся данные за последние сутки, но не за весь период существования кампании.
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Statistika/paths/~1adv~1v2~1fullstats/post
     *
     * @param array $params Запрос с датами
     *                      Запрос с интервалами
     *                      Запрос только с id кампаний
     *
     * @return array
     */
    public function statistic(array $params): array
    {
        return $this->postRequest('/adv/v2/fullstats', $params);
    }

    /**
     * Статистика кампаний V3
     *
     * Метод формирует статистику для кампаний независимо от типа.
     * Максимальный период в запросе — 31 день.
     * Для кампаний в статусах 7, 9 и 11.
     * @link https://dev.wildberries.ru/openapi/promotion#tag/Statistika/paths/~1adv~1v3~1fullstats/get
     */
    public function statisticV3(array $ids, DateTime|string $beginDate, DateTime|string $endDate): array
    {
        return $this->getRequest('/adv/v3/fullstats', [
            'ids'       => implode(',', $ids),
            'beginDate' => $beginDate instanceof DateTime ? $beginDate->format('Y-m-d') : $beginDate,
            'endDate'   => $endDate instanceof DateTime ? $endDate->format('Y-m-d') : $endDate,
        ]);
    }

    /**
     * Статистика по ключевым фразам
     *
     * Возвращает статистику по ключевым фразам за каждый день, когда кампания была активна.
     * За один запрос можно получить данные максимум за 7 дней.
     * Информация обновляется раз в час.
     * Максимум 4 запроса секунду
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Statistika/paths/~1adv~1v0~1stats~1keywords/get
     *
     * @param int      $id       Идентификатор кампании
     * @param DateTime $dateFrom Начало периода
     * @param DateTime $dateTo   Конец периода
     *
     * @return array
     */
    public function advertStatisticByKeywords(int $id, DateTime $dateFrom, DateTime $dateTo): array
    {
        return $this->getRequest('/adv/v0/stats/keywords', [
            'advert_id' => $id,
            'from' => $dateFrom->format('Y-m-d'),
            'to' => $dateTo->format('Y-m-d'),
        ])->keywords;
    }

    /**
     * Предметы для кампаний
     *
     * Возвращает предметы, номенклатуры из которых можно добавить в кампании.
     * Максимум 1 запрос в 12 секунд.
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Slovari/paths/~1adv~1v1~1supplier~1subjects/get
     *
     * @return array
     */
    public function subjects(): array
    {
        return $this->getRequest('/adv/v1/supplier/subjects');
    }

    /**
     * Номенклатуры для кампаний
     *
     * Возвращает номенклатуры, которые можно добавить в кампании.
     * Максимум 5 запросов в минуту
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Slovari/paths/~1adv~1v2~1supplier~1nms/post
     *
     * @param array $subjects ID предметов, для которых нужно получить номенклатуры
     *
     * @return array
     */
    public function nms(array $subjects = []): array
    {
        return $this->postRequest('/adv/v2/supplier/nms', $subjects);
    }

    /**
     * Конфигурационные значения
     * Возвращает информацию о допустимых значениях основных конфигурационных параметров кампаний.
     * Максимум 1 запрос в минуту
     * @deprecated Будет удален с 2026-02-02
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Prodvizhenie/paths/~1adv~1v0~1config/get
     */
    public function config(): object
    {
        return $this->getRequest('/adv/v0/config');
    }

    /**
     * Проверка типа РК
     *
     * @param int $type Тип РК
     * @param array $types Типы РК
     *
     * @throws InvalidArgumentException Неизвестный тип РК
     */
    private function checkType(int $type, array $types = [])
    {
        if (!in_array($type, $types ?: AdvertType::all())) {
            throw new InvalidArgumentException('Неизвестный тип РК: ' . $type);
        }
    }
}
