<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint;

use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\API\Response\ApiResponse;
use Dakword\WBSeller\Exception\ApiClientException;
use DateTimeInterface;
use InvalidArgumentException;

class Finances extends AbstractEndpoint
{
    public const SALES_REPORT_DETAILS_PATH = '/api/finance/v1/sales-reports/detailed';

    /**
     * Передавайте rrdId из последней строки предыдущей страницы до ответа HTTP 204.
     */
    public function salesReportDetails(
        DateTimeInterface $dateFrom,
        DateTimeInterface $dateTo,
        int $limit,
        int $rrdId = 0,
        string $period = 'weekly'
    ): ApiResponse {
        if ($limit < 1 || $limit > 100_000) {
            throw new InvalidArgumentException('Количество строк отчёта должно быть от 1 до 100 000');
        }

        if (!in_array($period, ['daily', 'weekly'], true)) {
            throw new InvalidArgumentException('Периодичность отчёта должна быть daily или weekly');
        }

        $response = $this->postRequest(self::SALES_REPORT_DETAILS_PATH, [
            'dateFrom' => $dateFrom->format(DATE_RFC3339),
            'dateTo' => $dateTo->format(DATE_RFC3339),
            'limit' => $limit,
            'rrdId' => $rrdId,
            'period' => $period,
        ]);

        if (!$response->isSuccessful()) {
            throw new ApiClientException(
                sprintf('Finance API: HTTP %d %s. %s', $response->statusCode, $response->reasonPhrase, mb_substr($response->text(), 0, 1000)),
                $response->statusCode,
                response: $response,
            );
        }

        return $response;
    }
}
