<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint;

use Dakword\WBSeller\API\Response\ApiResponse;

use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\Enum\ReturnAction;
use InvalidArgumentException;

class Returns extends AbstractEndpoint
{
    protected string $apiName = 'returns';

    /**
     * Заявки покупателей на возврат
     *
     * Возвращает заявки покупателей на возврат товаров за текущие 14 дней.
     *
     * @param bool  $archived Состояние заявки: true - в архиве, false - на рассмотрении
     * @param int   $page     Номер страницы
     * @param array $filter   Возможные ключи массива: id - UUID заявки, nmId - артикул WB
     * @param int   $limit    Количество заявок на странице
     *
     * @return ApiResponse
     *
     * @throws InvalidArgumentException Превышение максимального значения параметра limit
     */
    public function list(bool $archived, int $page = 1, array $filter = [], int $limit = 200): ApiResponse {
        $maxLimit = 200;
        if ($limit >  $maxLimit) {
            throw new InvalidArgumentException("Превышение максимального значения параметра limit: {$maxLimit}");
        }
        return $this->getRequest('/api/v1/claims', [
            'is_archive' => $archived,
            'limit' => $limit,
            'offset' => --$page * $limit,
        ] + (isset($filter['id']) ? [
            'id' => $filter['id']
        ] : []) + (isset($filter['nmId']) ? [
            'nmId' => $filter['nmId']
        ] : []));
    }

    /**
     * Ответ на заявку покупателя
     *
     * @param string $id      UUID заявки
     * @param string $action  Действие с заявкой (значение ReturnAction enum, например ReturnAction::APPROVE_CHECK->value)
     * @param string $comment Комментарий при ReturnAction::REJECT_CUSTOM->value
     *
     * @return ApiResponse
     *
     * @throws InvalidArgumentException Неизвестный ответ на заявку
     */
    public function action(string $id, string $action, string $comment = ''): ApiResponse {
        if (ReturnAction::tryFrom($action) === null) {
            throw new InvalidArgumentException('Неизвестный ответ на заявку: ' . $action);
        }
        return $this->patchRequest('/api/v1/claim', [
            'id' => $id,
            'action' => $action,
        ] + ($action === ReturnAction::REJECT_CUSTOM->value ? [
            'comment' => $comment,
        ] : []));
    }

}
