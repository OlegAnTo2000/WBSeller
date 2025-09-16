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
use Dakword\WBSeller\API\Endpoint\Subpoint\AdvSearchCatalog;

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
     * @param array $ids Список ID кампаний. Максимум 50
     *
     * @return array
     *
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
            'advertId' => $advertId,
            'type' => $type,
            'cpm' => $cpm,
            'param' => $param,
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
     *
     * @param array $params $ids, $beginDate, $endDate
     *
     * @return array
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
     *
     * Возвращает информацию о допустимых значениях основных конфигурационных параметров кампаний.
     * Максимум 1 запрос в минуту
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Prodvizhenie/paths/~1adv~1v0~1config/get
     */
    public function config(): object
    {
        return $this->getRequest('/adv/v0/config');
    }

    private function checkType(int $type, array $types = [])
    {
        if (!in_array($type, $types ?: AdvertType::all())) {
            throw new InvalidArgumentException('Неизвестный тип РК: ' . $type);
        }
    }
}
