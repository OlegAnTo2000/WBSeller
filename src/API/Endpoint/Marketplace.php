<?php
declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint;

use Dakword\WBSeller\API\Response\ApiResponse;

use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\API\Endpoint\Subpoint\CrossBorder;
use Dakword\WBSeller\API\Endpoint\Subpoint\DBS;
use Dakword\WBSeller\API\Endpoint\Subpoint\Passes;
use Dakword\WBSeller\API\Endpoint\Subpoint\Warehouses;
use Dakword\WBSeller\API\Endpoint\Subpoint\WBGO;
use DateTime;
use InvalidArgumentException;

class Marketplace extends AbstractEndpoint
{
    protected string $apiName = 'marketplace';

    /**
     * Методы используемые при кроссбордере
     */
    public function CrossBorder(): CrossBorder
    {
        return new CrossBorder($this);
    }

    /**
     * Доставка силами продавца
     */
    public function DBS(): DBS
    {
        return new DBS($this);
    }

    /**
     * Сервис для работы с пропусками.
     */
    public function Passes(): Passes
    {
        return new Passes($this);
    }

    /**
     * Сервис для работы со складами.
     */
    public function Warehouses(): Warehouses
    {
        return new Warehouses($this);
    }

    /**
     * Доставка курьером WB
     */
    public function WBGO(): WBGO
    {
        return new WBGO($this);
    }

    /**
     * Список поставок
     *
     * @param int $limit Параметр пагинации. Устанавливает предельное количество возвращаемых данных.
     * @param int $next  Параметр пагинации. Устанавливает значение, с которого надо получить следующий пакет данных.
     *                   Для получения полного списка данных должен быть равен 0 в первом запросе.
     *
     * @return ApiResponse
     *
     * @throws InvalidArgumentException Превышение максимального количества запрашиваемых данных
     */
    public function getSuppliesList(int $limit = 1_000, int $next = 0): ApiResponse {
        $maxLimit = 1_000;
        if ($limit > $maxLimit) {
            throw new InvalidArgumentException("Превышение максимального количества запрашиваемых данных: {$maxLimit}");
        }
        return $this->getRequest('/api/v3/supplies', ['limit' => $limit, 'next' => $next]);
    }

    /**
     * Создание новой поставки
     *
     * @param string $name Наименование поставки
     *
     * @return ApiResponse
     *
     * @throws InvalidArgumentException Превышение максимальной длинны наименования поставки
     */
    public function createSupply(string $name = ''): ApiResponse {
        $maxLength = 128;
        if (mb_strlen($name) > $maxLength) {
            throw new InvalidArgumentException("Превышение максимальной длинны наименования поставки: {$maxLength}");
        }
        return $this->postRequest('/api/v3/supplies', ['name' => $name]);
    }

    /**
     * Получить информацию о поставке
     *
     * @param string $supplyId Идентификатор поставки
     *
     * @return ApiResponse
     */
    public function getSupply(string $supplyId): ApiResponse {
        return $this->getRequest('/api/v3/supplies/' . $supplyId);
    }

    /**
     * Удалить поставку
     *
     * Удаляет поставку, если она активна и за ней не закреплено ни одно сборочное задание
     *
     * @param string $supplyId Идентификатор поставки
     */
    public function deleteSupply(string $supplyId): ApiResponse {
        return $this->deleteRequest('/api/v3/supplies/' . $supplyId);
    }

    /**
     * Список заказов, закреплённых за поставкой
     *
     * @param string $supplyId Идентификатор поставки
     *
     * @return ApiResponse
     */
    public function getSupplyOrders(string $supplyId): ApiResponse {
        return $this->getRequest('/api/v3/supplies/' . $supplyId . '/orders');
    }

    /**
     * Добавить к поставке сборочное задание
     *
     * Добавляет к поставке заказы и переводит их в статус confirm ("В сборке")
     * Также может перемещать сборочное задание между активными поставками, либо из закрытой в активную при условии,
     * что сборочное задание требует повторной отгрузки.
     * Добавить в поставку возможно только задания с соответствующим сКГТ-признаком (isLargeCargo),
     * либо если поставке ещё не присвоен сКГТ-признак (isLargeCargo = null).
     *
     * @param string $supplyId Идентификатор поставки
     * @param int    $orderId  Идентификатор сборочного задания
     *
     * @return ApiResponse
     */
    public function addSupplyOrder(string $supplyId, int $orderId): ApiResponse {
        return $this->patchRequest('/api/v3/supplies/' . $supplyId . '/orders/' . $orderId);
    }

    /**
     * Передать поставку в доставку (Закрытие поставки)
     *
     * Закрывает поставку и переводит все сборочные задания в ней в статус complete ("В доставке").
     * После закрытия поставки новые сборочные задания к ней добавить будет невозможно.
     * Передать поставку в доставку можно только при наличии в ней хотя бы одного сборочного задания.
     *
     * @param string $supplyId Идентификатор поставки
     *
     * @return ApiResponse
     */
    public function closeSupply(string $supplyId): ApiResponse {
        return $this->patchRequest('/api/v3/supplies/' . $supplyId . '/deliver');
    }

    /**
     * Получить все сборочные задания на повторную отгрузку
     *
     * Возвращает все сборочные задания, требующие повторной отгрузки.
     *
     * @return ApiResponse
     * @return ApiResponse
     */
    public function getReShipmentOrdersSupplies(): ApiResponse {
        return $this->getRequest('/api/v3/supplies/orders/reshipment');
    }

    /**
     * QR поставки в заданном формате
     *
     * Возвращает QR в svg, zplv (вертикальный), zplh (горизонтальный), png.
     * Можно получить, только если поставка передана в доставку.
     *
     * @param string $supplyId Идентификатор поставки
     * @param string $type     Формат штрихкода ("svg", "zplv", "zplh", "png")
     *
     * @return ApiResponse
     *
     * @throws InvalidArgumentException Неизвестный формат штрихкода
     */
    public function getSupplyBarcode(string $supplyId, string $type): ApiResponse {
        if (!in_array($type, ['svg', 'zplv', 'zplh', 'png'])) {
            throw new InvalidArgumentException('Неизвестный формат штрихкода: ' . $type);
        }
        return $this->getRequest('/api/v3/supplies/' . $supplyId . '/barcode', ['type' => $type]);
    }

    /**
     * Отменить сборочное задание
     *
     * Переводит сборочное задание в статус cancel ("Отменено продавцом").
     *
     * @param int $orderId Идентификатор сборочного задания
     *
     * @return ApiResponse
     */
    public function cancelOrder(int $orderId): ApiResponse {
        return $this->patchRequest('/api/v3/orders/' . $orderId . '/cancel');
    }

    /**
     * Получить статусы сборочных заданий
     *
     * Возвращает статусы сборочных заданий по переданному списку идентификаторов сборочных заданий.
     * supplierStatus - статус сборочного задания, триггером изменения которого является сам продавец.
     * wbStatus - статус сборочного задания в системе Wildberries.
     *
     * @param array $orders Список идентификаторов сборочных заданий
     *
     * @return ApiResponse
     * @return ApiResponse
     *
     * @throws InvalidArgumentException Превышение максимального количества запрашиваемых статусов сборочных заданий
     */
    public function getOrdersStatuses(array $orders): ApiResponse {
        $maxCount = 1_000;
        if (count($orders) > $maxCount) {
            throw new InvalidArgumentException("Превышение максимального количества запрашиваемых статусов сборочных заданий: {$maxCount}");
        }
        return $this->postRequest('/api/v3/orders/status', ['orders' => $orders]);
    }

    /**
     * Получить информацию по сборочным заданиям
     *
     * Возвращает информацию по сборочным заданиям без их актуального статуса.
     * Данные по сборочному заданию, возвращающиеся в данном методе, не меняются.
     * Рекомендуется использовать для получения исторических данных.
     * Можно выгрузить данные за конкретный период, максимум 30 календарных дней
     *
     * @param int      $limit     Параметр пагинации. Устанавливает предельное количество возвращаемых данных. (не более 1000)
     * @param int      $next      Параметр пагинации. Устанавливает значение, с которого надо получить следующий пакет данных. Для получения полного списка данных должен быть равен 0 в первом запросе.
     * @param ?DateTime $dateStart С какой даты вернуть сборочные задания (заказы)
     *                            по умолчанию — дата за 30 дней до запроса
     * @param ?DateTime $dateEnd   По какую дату вернуть сборочные задания (заказы)
     *
     * @return ApiResponse
     *
     * @throws InvalidArgumentException Превышение значения параметра limit
     */
    public function getOrders(int $limit = 1_000, int $next = 0, ?DateTime $dateStart = null, ?DateTime $dateEnd = null): ApiResponse {
        $maxLimit = 1_000;
        if ($limit > $maxLimit) {
            throw new InvalidArgumentException("Превышение максимального количества запрашиваемых строк: {$maxLimit}");
        }
        return $this->getRequest('/api/v3/orders',
            ['limit' => $limit, 'next' => $next]
            + ($dateStart == '' ? [] : ['dateFrom' => $dateStart->getTimestamp()])
            + ($dateEnd == '' ? [] : ['dateTo' => $dateEnd->getTimestamp()])
        );
    }

    /**
     * Получить список новых сборочных заданий
     *
     * Возвращает список всех новых сборочных заданий у продавца на данный момент.
     *
     * @return ApiResponse
     */
    public function getNewOrders(): ApiResponse {
        return $this->getRequest('/api/v3/orders/new');
    }

    /**
     * Закрепить за сборочным заданием КиЗ (маркировку Честного знака)
     *
     * @param int   $orderId Идентификатор сборочного задания
     * @param array $sgtin   Массив КиЗов (У одного сборочного задания не может быть больше 24 маркировок)
     *
     * @throws InvalidArgumentException Превышение максимального количества элементов переданного массива
     */
    public function setOrderKiz(int $orderId, array $sgtin): ApiResponse {
        $maxCount = 24;
        if (count($sgtin) > $maxCount) {
            throw new InvalidArgumentException("Превышение максимального количества элементов переданного массива: {$maxCount}");
        }
        return $this->putRequest('/api/v3/orders/' . $orderId . '/meta/sgtin', ['sgtins' => $sgtin]);
    }

    /**
     * Закрепить за сборочным заданием УИН
     *
     * Обновляет УИН (уникальный идентификационный номер) сборочного задания.
     * У одного сборочного задания может быть только один УИН.
     * Добавлять маркировку можно только для заказов в статусе confirm.
     *
     * @param int    $orderId Идентификатор сборочного задания
     * @param string $uin     УИН (16 символов)
     *
     * @return ApiResponse
     */
    public function setOrderUin(int $orderId, string $uin): ApiResponse {
        return $this->putRequest('/api/v3/orders/' . $orderId . '/meta/uin', ['uin' => $uin]);
    }

    /**
     * Закрепить за сборочным заданием IMEI
     *
     * Обновляет IMEI сборочного задания.
     * У одного сборочного задания может быть только один IMEI.
     * Добавлять маркировку можно только для заказов в статусе confirm.
     *
     * @param int    $orderId Идентификатор сборочного задания
     * @param string $imei    IMEI (15 символов)
     *
     * @return ApiResponse
     */
    public function setOrderIMEI(int $orderId, string $imei): ApiResponse {
        return $this->putRequest('/api/v3/orders/' . $orderId . '/meta/imei', ['imei' => $imei]);
    }

    /**
     * Закрепить за сборочным заданием GTIN
     *
     * Обновляет GTIN сборочного задания.
     * У одного сборочного задания может быть только один GTIN.
     * Добавлять маркировку можно только для заказов в статусе confirm.
     *
     * @param int    $orderId Идентификатор сборочного задания
     * @param string $gtin    УИН (13 символов)
     *
     * @return ApiResponse
     */
    public function setOrderGTIN(int $orderId, string $gtin): ApiResponse {
        return $this->putRequest('/api/v3/orders/' . $orderId . '/meta/gtin', ['gtin' => $gtin]);
    }

    /**
     * Получить метаданные сборочного задания
     *
     * Возвращает метаданные заказа (imei, uin, gtin, sgtin)
     *
     * @param int $orderId Идентификатор сборочного задания
     *
     * @return ApiResponse
     */
    public function getOrderMeta(int $orderId): ApiResponse {
        return $this->getRequest('/api/v3/orders/' . $orderId . '/meta');
    }

    /**
     * Удалить метаданные сборочного задания
     *
     * @param int     $orderId Идентификатор сборочного задания
     * @param string $key      Название метаданных для удаления (imei, uin, gtin, sgtin)
     *
     * @return ApiResponse
     *
     * @throws InvalidArgumentException Неизвестное название метаданных
     */
    public function deleteOrderMeta(int $orderId, string $key): ApiResponse {
        if (!in_array($key, ['imei', 'uin', 'gtin', 'sgtin'])) {
            throw new InvalidArgumentException('Неизвестное название метаданных: ' . $key);
        }
        return $this->deleteRequest('/api/v3/orders/' . $orderId . '/meta', [
            'key' => $key
        ]);
    }

    /**
     * Получить этикетки для сборочных заданий
     *
     * Возвращает список этикеток по переданному массиву сборочных заданий.
     * Можно запросить этикетку в формате svg, zplv (вертикальный), zplh (горизонтальный), png.
     * Метод возвращает этикетки только для сборочных заданий, находящихся на сборке (в статусе confirm)
     * Доступные размеры: 580х400 и 400х300 пикселей.
     *
     * @param array  $orderIds Идентификаторы сборочных заданий (не более 100)
     * @param string $type     Формат штрихкода ("svg", "zplv", "zplh", "png")
     * @param string $size     Размер этикетки ("40x30", "58x40")
     *
     * @return ApiResponse
     *
     * @throws InvalidArgumentException Неизвестный формат штрихкода
     * @throws InvalidArgumentException Неизвестный размер этикетки
     * @throws InvalidArgumentException Превышение максимального количества запрашиваемых этикеток
     */
    public function getOrdersStickers(array $orderIds, string $type, string $size): ApiResponse {
        $maxCount = 100;
        if (count($orderIds) > $maxCount) {
            throw new InvalidArgumentException("Превышение максимального количества запрашиваемых этикеток: {$maxCount}");
        }
        if (!in_array($type, ['svg', 'zplv', 'zplh', 'png'])) {
            throw new InvalidArgumentException('Неизвестный формат штрихкода: ' . $type);
        }
        if (!in_array($size, ['40x30', '58x40'])) {
            throw new InvalidArgumentException('Неизвестный размер этикетки: ' . $type);
        }
        return $this->postRequest(
            '/api/v3/orders/stickers?type=' . $type . '&width=' . explode('x', $size)[0] . '&height=' . explode('x', $size)[1],
            ['orders' => $orderIds]);
    }

    /**
     * Обновление остатков товаров по складу
     *
     * @param int   $warehouseId Идентификатор склада продавца
     * @param array $stocks      Массив баркодов товаров и их остатков (не более 1000)
     *
     * @throws InvalidArgumentException Превышение максимального количества обновляемых остатков
     */
    public function updateWarehouseStocks(int $warehouseId, array $stocks): ApiResponse {
        $maxCount = 1_000;
        if (count($stocks) > $maxCount) {
            throw new InvalidArgumentException("Превышение максимального количества обновляемых остатков: {$maxCount}");
        }
        return $this->putRequest('/api/v3/stocks/' . $warehouseId, ['stocks' => $stocks]);
    }

    /**
     * Удаление остатков товаров по складу
     *
     * Действие необратимо. Удаленный остаток будет необходимо загрузить повторно для возобновления продаж.
     *
     * @param int   $warehouseId Идентификатор склада продавца
     * @param array $skus        Массив баркодов (не более 1000)
     *
     * @throws InvalidArgumentException Превышение максимального количества удаляемых остатков
     */
    public function deleteWarehouseStocks(int $warehouseId, array $skus): ApiResponse {
        $maxCount = 1_000;
        if (count($skus) > $maxCount) {
            throw new InvalidArgumentException("Превышение максимального количества удаляемых остатков: {$maxCount}");
        }
        return $this->deleteRequest('/api/v3/stocks/' . $warehouseId, ['skus' => $skus]);
    }

    /**
     * Получить остатки товаров по складу
     *
     * @param int   $warehouseId Идентификатор склада продавца
     * @param array $skus        Массив баркодов (не более 1000)
     *
     * @return ApiResponse
     *
     * @throws InvalidArgumentException Превышение максимального количества запрашиваемых остатков
     */
    public function getWarehouseStocks(int $warehouseId, array $skus): ApiResponse {
        $maxCount = 1_000;
        if (count($skus) > $maxCount) {
            throw new InvalidArgumentException("Превышение максимального количества запрашиваемых остатков: {$maxCount}");
        }
        return $this->postRequest('/api/v3/stocks/' . $warehouseId, ['skus' => $skus]);
    }

    /**
     * Получить список коробов поставки
     *
     * @param string $supplyId Идентификатор поставки
     *
     * @return ApiResponse
     */
    public function getSupplyBoxes(string $supplyId): ApiResponse {
        return $this->getRequest('/api/v3/supplies/' . $supplyId . '/trbx');
    }

    /**
     * Добавить короба к поставке
     *
     * Добавляет требуемое количество коробов в поставку.
     * Можно добавить, только пока поставка на сборке.
     *
     * @param string $supplyId Идентификатор поставки
     * @param int    $amount   Количество коробов, которые необходимо добавить к поставке
     *
     * @return ApiResponse
     *
     * @throws InvalidArgumentException ревышение максимального количества добавляемых коробов
     */
    public function addSupplyBoxes(string $supplyId, int $amount = 1): ApiResponse {
        $maxCount = 1_000;
        if ($amount > $maxCount) {
            throw new InvalidArgumentException("Превышение максимального количества добавляемых коробов: {$maxCount}");
        }
        return $this->postRequest('/api/v3/supplies/' . $supplyId . '/trbx', ['amount' => $amount]);
    }

    /**
     * Удалить короба из поставки
     *
     * Убирает заказы из перечисленных коробов поставки и удаляет короба.
     * Можно удалить, только пока поставка на сборке.
     *
     * @param string $supplyId Идентификатор поставки
     * @param array  $boxeIds Список ID коробов, которые необходимо удалить
     *
     * @return ApiResponse
     */
    public function deleteSupplyBoxes(string $supplyId, array $boxeIds): ApiResponse {
        return $this->deleteRequest('/api/v3/supplies/' . $supplyId . '/trbx', ['trbxIds' => $boxeIds]);
    }

    /**
     * Добавить заказы к коробу
     *
     * Добавляет заказы в короб для выбранной поставки.
     * Можно добавить, только пока поставка на сборке.
     *
     * @param string $supplyId Идентификатор поставки
     * @param string $boxId    ID короба
     * @param array  $orderIds Список заказов, которые необходимо добавить в короб
     *
     * @return ApiResponse
     */
    public function addBoxOrders(string $supplyId, string $boxId, array $orderIds): ApiResponse {
        return $this->patchRequest('/api/v3/supplies/' . $supplyId . '/trbx/' . $boxId, ['orderIds' => $orderIds]);
    }

    /**
     * Удалить заказ из короба
     *
     * Удаляет заказ из короба выбранной поставки.
     * Можно удалить, только пока поставка на сборке.
     *
     * @param string $supplyId Идентификатор поставки
     * @param string $boxId    ID короба
     * @param int    $orderId  ID сборочного задания
     *
     * @return ApiResponse
     */
    public function deleteBoxOrder(string $supplyId, string $boxId, int $orderId): ApiResponse {
        return $this->deleteRequest('/api/v3/supplies/' . $supplyId . '/trbx/' . $boxId . '/orders/' . $orderId);
    }

    /**
     * Получить стикеры коробов поставки
     *
     * Возвращает стикеры QR в svg, zplv (вертикальный), zplh (горизонтальный), png.
     * Можно получить, только если в коробе есть заказы.
     * Размер стикеров: 580x400 пикселей
     *
     * @param string $supplyId Идентификатор поставки
     * @param array  $boxIds   Список ID коробов, по которым необходимо вернуть стикеры
     * @param string $type     Формат штрихкода ("svg", "zplv", "zplh", "png")
     *
     * @return ApiResponse
     *
     * @throws InvalidArgumentException Неизвестный формат штрихкода
     */
    public function getSupplyBoxStickers(string $supplyId, array $boxIds, string $type = 'svg'): ApiResponse {
        if (!in_array($type, ['svg', 'zplv', 'zplh', 'png'])) {
            throw new InvalidArgumentException('Неизвестный формат штрихкода: ' . $type);
        }
        return $this->postRequest('/api/v3/supplies/' . $supplyId . '/trbx/stickers?type=' . $type, ['trbxIds' => $boxIds]);
    }

}
