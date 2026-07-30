<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint\Subpoint;

use Dakword\WBSeller\API\Response\ApiResponse;

use Dakword\WBSeller\API\Endpoint\Common;
use DateTimeInterface;
use InvalidArgumentException;

class News
{
    private Common $Common;

    public function __construct(Common $Common)
    {
        $this->Common = $Common;
    }

    /**
     * Новости портала продавцов по дате публикации или ID новости.
     *
     * Необходимо передать хотя бы один из параметров.
     *
     * @param DateTimeInterface|null $from   Дата, начиная с которой нужно получить новости
     * @param int|null               $fromId ID новости, начиная с которой — включая её — нужно получить новости
     */
    public function list(?DateTimeInterface $from = null, ?int $fromId = null): ApiResponse
    {
        if ($from === null && $fromId === null) {
            throw new InvalidArgumentException('Необходимо указать дату или ID новости');
        }
        if ($fromId !== null && $fromId < 0) {
            throw new InvalidArgumentException('ID новости не может быть отрицательным');
        }

        return $this->Common->getRequest(
            '/api/communications/v2/news',
            ($from !== null ? ['from' => $from->format('Y-m-d')] : [])
            + ($fromId !== null ? ['fromID' => $fromId] : [])
        );
    }

    /**
     * Новости портала продавцов
     *
     * Метод позволяет получать новости с портала продавцов в формате HTML.
     * За один запрос можно получить не более 100 новостей.
     * @link https://openapi.wildberries.ru/general/sellers_portal_news/ru/#/paths/~1api~1communications~1v1~1news/get
     *
     * @param \DateTime $date Дата, от которой необходимо выдать новости
     *
     * @return ApiResponse
     * @deprecated Используйте list(), работающий с актуальным маршрутом v2.
     */
    public function fromDate(\DateTime $date): ApiResponse {
        return $this->Common->getRequest('/api/communications/v1/news', [
            'from' => $date->format('Y-m-d'),
        ]);
    }

    /**
     * Новости портала продавцов
     *
     * Метод позволяет получать новости с портала продавцов в формате HTML.
     * За один запрос можно получить не более 100 новостей.
     * Допускается 1 запрос в 10 минут.
     * @link https://openapi.wildberries.ru/general/sellers_portal_news/ru/#/paths/~1api~1communications~1v1~1news/get
     *
     * @param int $id ID новости, от которой необходимо выдать новости
     *
     * @return ApiResponse
     * @deprecated Используйте list(), работающий с актуальным маршрутом v2.
     */
    public function fromId(int $id): ApiResponse {
        return $this->Common->getRequest('/api/communications/v1/news', [
            'fromID' => $id,
        ]);
    }
}
