<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint\Subpoint;

use Dakword\WBSeller\API\Response\ApiResponse;

use InvalidArgumentException;
use Dakword\WBSeller\API\Endpoint\Marketplace;

class WBGO
{
    private Marketplace $Marketplace;

    public function __construct(Marketplace $Marketplace)
    {
        $this->Marketplace = $Marketplace;
    }

    /**
     * Перевести на сборку
     *
     * Переводит сборочное задание в статус confirm ("На сборке")
     * @link https://openapi.wb.ru/marketplace/api/ru/#tag/Dostavka-kurerom-WB-(WBGO)/paths/~1api~1v3~1orders~1{orderId}~1confirm/patch
     *
     * @param int $order_id Идентификатор сборочного задания
     */
    public function confirm(int $order_id): ApiResponse {
        return $this->Marketplace->patchRequest("/api/v3/orders/{$order_id}/confirm");
    }

    /**
     * Перевести в доставку
     *
     * Переводит сборочное задание в статус complete ("В доставке")
     * @link https://openapi.wb.ru/marketplace/api/ru/#tag/Dostavka-kurerom-WB-(WBGO)/paths/~1api~1v3~1orders~1{orderId}~1assemble/patch
     *
     * @param int $order_id Идентификатор сборочного задания
     */
    public function assemble(int $order_id): ApiResponse {
        return $this->Marketplace->patchRequest("/api/v3/orders/{$order_id}/assemble");
    }

    /**
     * Список контактов
     *
     * Возвращает список контактов, привязанных к складу продавца.
     * @link https://openapi.wb.ru/marketplace/api/ru/#tag/Dostavka-kurerom-WB-(WBGO)/paths/~1api~1v3~1warehouses~1{warehouseId}~1contacts/get
     *
     * @param int $warehouse_id Идентификатор склада продавца
     *
     * @return ApiResponse
     */
    public function getContacts(int $warehouse_id): ApiResponse {
        return $this->Marketplace->getRequest("/api/v3/warehouses/{$warehouse_id}/contacts");
    }

    /**
     * Обновить список контактов
     *
     * Работает по принципу перезаписи: всё, что указано в contacts, ставится взамен того, что было ранее.
     * Только для складов с типом доставки 3 - курьером WB.
     * К складу можно добавить до 5 контактов.
     * Чтобы удалить контакты, отправьте пустой массив contacts.
     * @link https://openapi.wb.ru/marketplace/api/ru/#tag/Dostavka-kurerom-WB-(WBGO)/paths/~1api~1v3~1warehouses~1{warehouseId}~1contacts/put
     *
     * @param int   $warehouse_id Идентификатор склада продавца
     * @param array $contacts     Контакты [{comment: string, phone: string}, ...]
     *
     * @throws InvalidArgumentException Превышение максимального количества контактов
     */
    public function updateContacts(int $warehouse_id, array $contacts): ApiResponse {
        $maxLimit = 5;
        if (count($contacts) > $maxLimit) {
            throw new InvalidArgumentException("Превышение максимального количества контактов: {$maxLimit}");
        }
        return $this->Marketplace->putRequest("/api/v3/warehouses/{$warehouse_id}/contacts", [
            'contacts' => $contacts,
        ]);
    }
}