<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint\Subpoint;

use Dakword\WBSeller\API\Response\ApiResponse;

use Dakword\WBSeller\API\Endpoint\Adv;
use Dakword\WBSeller\Enum\AdvertDepositSource;
use Dakword\WBSeller\Enum\AdvertType;
use DateTime;
use InvalidArgumentException;

class AdvFinance
{
    private Adv $Adv;

    public function __construct(Adv $Adv)
    {
        $this->Adv = $Adv;
    }

    /**
     * Баланс
     *
     * Метод позволяет получать информацию о счёте, балансе и бонусах продавца.
     * Допускается 1 запрос в секунду.
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Finansy/paths/~1adv~1v1~1balance/get
     */
    public function balance(): ApiResponse {
        return $this->Adv->getRequest('/adv/v1/balance');
    }

    /**
     * Бюджет кампании
     *
     * Метод позволяет получать информацию о бюджете кампании.
     * Допускается 4 запроса в секунду.
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Finansy/paths/~1adv~1v1~1budget/get
     *
     * @param int $id Идентификатор кампании
     *
     * @return ApiResponse
     */
    public function getAdvertBudget(int $id): ApiResponse {
        return $this->Adv->getRequest('/adv/v1/budget', ['id' => $id]);
    }

    /**
     * Пополнение бюджета кампании
     *
     * Допускается 1 запрос в секунду.
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Finansy/paths/~1adv~1v1~1budget~1deposit/post
     *
     * @param int $id            Идентификатор кампании
     * @param int $summa         Сумма пополнения (min. 500 ₽)
     * @param int $depositSource Тип источника пополнения AdvertDepositSource
     *
     * @return ApiResponse
     *
     * @throws InvalidArgumentException Минимальная сумма пополнения
     * @throws InvalidArgumentException Неизвестный тип источника пополнения
     */
    public function depositAdvertBudget(int $id, int $summa, int $depositSource): ApiResponse {
        $minSumma = 500;
        if ($summa < $minSumma) {
            throw new InvalidArgumentException('Минимальная сумма пополнения: ' . $minSumma);
        }
        if (AdvertDepositSource::tryFrom($depositSource) === null) {
            throw new InvalidArgumentException('Неизвестный тип источника пополнения: ' . $depositSource);
        }
        return $this->Adv->postRequest('/adv/v1/budget/deposit?id=' . $id, [
            'sum' => $summa,
            'type' => $depositSource,
            'return' => true,
        ]);
    }

    /**
     * История пополнений счета
     *
     * Минимальный интервал 1 день, максимальный 31
     * Допускается 1 запрос в секунду
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Finansy/paths/~1adv~1v1~1payments/get
     *
     * @param DateTime $dateFrom Начало интервала
     * @param DateTime $dateTo   Конец интервала
     *
     * @return ApiResponse
     */
    public function payments(DateTime $dateFrom, DateTime $dateTo): ApiResponse {
        return $this->Adv->getRequest('/adv/v1/payments', [
            'from' => $dateFrom->format('Y-m-d'),
            'to' => $dateTo->format('Y-m-d'),
        ]);
    }

    /**
     * История затрат
     *
     * Минимальный интервал 1 день, максимальный 31
     * Допускается 1 запрос в секунду
     * @link https://openapi.wb.ru/promotion/api/ru/#tag/Finansy/paths/~1adv~1v1~1upd/get
     *
     * @param DateTime $dateFrom Начало интервала
     * @param DateTime $dateTo   Конец интервала
     *
     * @return ApiResponse
     */
    public function costs(DateTime $dateFrom, DateTime $dateTo): ApiResponse {
        return $this->Adv->getRequest('/adv/v1/upd', [
            'from' => $dateFrom->format('Y-m-d'),
            'to' => $dateTo->format('Y-m-d'),
        ]);
    }
}
