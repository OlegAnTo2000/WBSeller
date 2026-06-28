<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint;

use Dakword\WBSeller\API\Response\ApiResponse;

use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\API\Endpoint\Subpoint\Templates;
use InvalidArgumentException;
use DateTime;

class Feedbacks extends AbstractEndpoint
{
    protected string $apiName = 'feedbacks';

    /**
     * Сервис для работы с шаблонами ответов.
     *
     * @return Templates
     */
    public function Templates(): Templates
    {
        return new Templates($this);
    }

    /**
     * Наличие непросмотренных отзывов
     *
     * Метод отображает информацию о наличии у продавца непросмотренных отзывов и вопросов
     *
     * @return ApiResponse
     * 	    data: {hasNewQuestions: bool, hasNewFeedbacks: bool},
     * 	    error: bool, errorText: string, additionalErrors: ?string
     * }
     */
    public function hasNew(): ApiResponse {
        return $this->getRequest('/api/v1/new-feedbacks-questions');
    }

    /**
     * Количество отзывов
     *
     * @param bool          $isAnswered Обработанные отзывы (true) или необработанные отзывы (false)
     *                                  Если не указать, вернутся необработанные отзывы
     * @param DateTime|null $dateStart  Дата начала периода
     * @param DateTime|null $dateEnd    Дата конца периода
     *
     * @return ApiResponse
     * 	    data: {hasNewQuestions: bool, hasNewFeedbacks: bool},
     * 	    error: bool, errorText: string, additionalErrors: ?string
     * }
     */
    public function count(bool $isAnswered = false, ?DateTime $dateStart = null, ?DateTime $dateEnd = null): ApiResponse {
        return $this->getRequest('/api/v1/feedbacks/count', [
                'isAnswered' => $isAnswered
            ]
            + ($dateStart == '' ? [] : ['dateFrom' => $dateStart->getTimestamp()])
            + ($dateEnd == '' ? [] : ['dateTo' => $dateEnd->getTimestamp()])
        );
    }

    /**
     * Необработанные отзывы
     *
     * Метод позволяет получить количество необработанных отзывов за сегодня,
     * за всё время, и среднюю оценку всех отзывов
     *
     * @return ApiResponse
     * 	    data: {countUnanswered: int, countUnansweredToday: int, valuation: string},
     * 	    error: bool, errorText: string, additionalErrors: ?string
     * }
     */
    public function unansweredCount(): ApiResponse {
        return $this->getRequest('/api/v1/feedbacks/count-unanswered');
    }

    /**
     * Список отзывов
     *
     * Метод позволяет получить список отзывов по заданным параметрам с пагинацией и сортировкой
     *
     * @param int         $page       Номер страницы
     * @param int         $onPage     Количество отзывов на странице
     * @param bool        $isAnswered Обработанные отзывы (true) или необработанные отзывы (false)
     * @param int         $nmId       Идентификатор номенклатуры
     * @param string|null $order      Сортировка отзывов по дате "dateAsc" / "dateDesc"
     * @param DateTime    $dateFrom   Дата начала периода
     * @param DateTime    $dateTo     Дата окончания периода
     *
     * @return ApiResponse
     * 	    data: {countUnanswered: int, countArchive: int, feedbacks: [object, ...]},
     * 	    error: bool, errorText: string, additionalErrors: ?string
     *
     * @throws InvalidArgumentException Превышение максимального количества запрошенных отзывов
     * @throws InvalidArgumentException Недопустимое значение для сортировки результатов
     */
    public function list(int $page = 1, int $onPage = 1_000, bool $isAnswered = false, int $nmId = 0, ?string $order = null,
        ?\DateTime $dateFrom = null, ?\DateTime $dateTo = null
    ): ApiResponse {
        $maxCount = 1_000;
        if ($onPage > $maxCount) {
            throw new InvalidArgumentException("Превышение максимального количества запрошенных отзывов: {$maxCount}");
        }
        $this->checkOrder($order);
        return $this->getRequest('/api/v1/feedbacks', [
                'isAnswered' => $isAnswered,
                'skip' => --$page * $onPage,
                'take' => $onPage,
            ]
            + ($nmId ? ['nmId' => $nmId] : [])
            + (!is_null($order) ? ['order' => $order] : [])
            + (!is_null($dateFrom) ? ['dateFrom' => $dateFrom->getTimestamp()] : [])
            + (!is_null($dateTo) ? ['dateTo' => $dateTo->getTimestamp()] : [])
        );
    }

    /**
     * Список архивных отзывов
     *
     * Отзыв становится архивным если на него предоставлен ответ
     * или ответ не предоставлен в течение 30 дней со дня его публикации
     *
     * @param int         $page   Номер страницы
     * @param int         $onPage Количество отзывов на странице
     * @param int         $nmId   Идентификатор номенклатуры
     * @param string|null $order  Сортировка отзывов по дате "dateAsc" / "dateDesc"
     *
     * @return ApiResponse
     * 	    data: {feedbacks: [object, ...]},
     * 	    error: bool, errorText: string, additionalErrors: ?string
     *
     * @throws InvalidArgumentException Превышение максимального количества запрошенных отзывов
     * @throws InvalidArgumentException Недопустимое значение для сортировки результатов
     */
    public function archive(int $page = 1, int $onPage= 1_000, int $nmId = 0, ?string $order = null): ApiResponse {
        $maxCount = 1_000;
        if ($onPage > $maxCount) {
            throw new InvalidArgumentException("Превышение максимального количества запрошенных отзывов: {$maxCount}");
        }
        $this->checkOrder($order);
        return $this->getRequest('/api/v1/feedbacks/archive', [
                'skip' => --$page * $onPage,
                'take' => $onPage,
            ]
            + ($nmId ? ['nmId' => $nmId] : [])
            + (!is_null($order) ? ['order' => $order] : [])
        );
    }

    /**
     * Получение отзывов в формате XLSX
     *
     * Метод позволяет получить XLSX файл с отзывами в кодировке BASE64
     * За один запрос можно получить 5000 отзывов.
     * На данный момент всего можно получить 200 000 последних отзывов.
     *
     * @param bool        $isAnswered           Обработанные отзывы (true) или необработанные отзывы (false)
     * @param int         $page                 Номер страницы
     *
     * @return ApiResponse
     * 	    data: {filename: string, contentType: string, file: base64},
     * 	    error: bool, errorText: string, additionalErrors: ?string
     */
    public function xlsReport(bool $isAnswered = false,int $page = 1): ApiResponse {
        return $this->getRequest('/api/v1/feedbacks/report', [
                'isAnswered' => $isAnswered,
                'skip' => --$page * 5_000,
            ]);
    }

    /**
     * Ответить на отзыв
     *
     * @param string $id         Идентификатор отзыва
     * @param string $answerText Текст ответа
     *
     * @return ApiResponse
     */
    public function sendAnswer(string $id, string $answerText): ApiResponse {
        return $this->postRequest('/api/v1/feedbacks/answer', [
            'id' => $id,
            'text' => $answerText,
        ]);
    }

    /**
     * Отредактировать ответ на отзыв
     *
     * @param string $id         Идентификатор отзыва
     * @param string $answerText Текст ответа
     *
     * @return ApiResponse
     */
    public function updateAnswer(string $id, string $answerText): ApiResponse {
        return $this->patchRequest('/api/v1/feedbacks/answer', [
            'id' => $id,
            'text' => $answerText,
        ]);
    }

    /**
     * Получить отзыв
     *
     * @param string $id Идентификатор отзыва
     *
     * @return ApiResponse
     * 	    data: object,
     * 	    error: bool, errorText: string, additionalErrors: any
     * }
     */
    public function get(string $id): ApiResponse {
        return $this->getRequest('/api/v1/feedback', [
            'id' => $id,
        ]);
    }

    /**
     * Получить список оценок
     *
     * @return ApiResponse
     * 	    data: {feedbackValuations: object, productValuations: object},
     * 	    error: bool, errorText: string, additionalErrors: any
     * }
     */
    public function ratesList(): ApiResponse {
        return $this->getRequest('/api/v1/supplier-valuations', [], [
            'X-Locale' => getenv('WBSELLER_LOCALE')?:'ru'
        ]);
    }

    /**
     * Пожаловаться на отзыв
     * @link https://openapi.wb.ru/feedbacks-questions/api/ru/#tag/Rabota-s-otzyvami/paths/~1api~1v1~1feedbacks~1actions/post
     *
     * @param string $id             Причина жалобы на отзыв
     * @param int    $feedbackRateId Оценка отзыва
     *
     * @return ApiResponse
     */
    public function rateFeedback(string $id, int $feedbackRateId): ApiResponse {
        return $this->postRequest('/api/v1/feedbacks/actions', [
            'id' => $id,
            'supplierFeedbackValuation' => $feedbackRateId,
        ]);
    }

    /**
     * Сообщить о проблеме с товаром
     * @link https://openapi.wb.ru/feedbacks-questions/api/ru/#tag/Rabota-s-otzyvami/paths/~1api~1v1~1feedbacks~1actions/post
     *
     * @param string $id            Идентификатор отзыва
     * @param int    $productRateId Причина жалобы на отзыв
     *
     * @return ApiResponse
     */
    public function rateProduct(string $id, int $productRateId): ApiResponse {
        return $this->postRequest('/api/v1/feedbacks/actions', [
            'id' => $id,
            'supplierProductValuation' => $productRateId,
        ]);
    }

    /**
     * Пожаловаться на отзыв, сообщить о проблеме с товаром
     * @link https://openapi.wb.ru/feedbacks-questions/api/ru/#tag/Rabota-s-otzyvami/paths/~1api~1v1~1feedbacks~1actions/post
     *
     * @param string $id             Идентификатор отзыва
     * @param int    $feedbackRateId Причина жалобы на отзыв
     * @param int    $productRateId  Описание проблемы товара
     *
     * @return ApiResponse
     */
    public function rate(string $id, int $feedbackRateId, int $productRateId): ApiResponse {
        return $this->postRequest('/api/v1/feedbacks/actions', [
            'id' => $id,
            'supplierFeedbackValuation' => $feedbackRateId,
            'supplierProductValuation' => $productRateId,
        ]);
    }

    /**
     * Возврат товара по ID отзыва
     *
     * Метод позволяет запросить на возврат товар, по которому оставлен отзыв.
     * Возврат доступен для отзывов с "isAbleReturnProductOrders": true
     * @link https://openapi.wb.ru/feedbacks-questions/api/ru/#tag/Otzyvy/paths/~1api~1v1~1feedbacks~1order~1return/post
     *
     * @param string $id Идентификатор отзыва
     *
     * @return ApiResponse
     */
    public function orderReturn(string $id): ApiResponse {
        return $this->postRequest('/api/v1/feedbacks/order/return', [
            'feedbackId' => $id,
        ]);
    }

    private function checkOrder($order)
    {
        if (!is_null($order) && !in_array($order, ['dateAsc', 'dateDesc'])) {
            throw new InvalidArgumentException("Недопустимое значение для сортировки результатов: {$order}");
        }
    }
}
