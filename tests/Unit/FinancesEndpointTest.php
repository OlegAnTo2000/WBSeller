<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Tests\Unit;

use Dakword\WBSeller\API;
use Dakword\WBSeller\Exception\ApiClientException;
use Dakword\WBSeller\Tests\TestCase;
use DateTimeImmutable;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

class FinancesEndpointTest extends TestCase
{
    public function testSalesReportDetailsUsesFinanceHostAndClientProxy(): void
    {
        $mock = new MockHandler([new Response(204)]);
        $api = new API([
            'keys' => ['finances' => 'fake-finance-key'],
            'masterkey' => 'fake-master-key',
            'proxy' => 'http://proxy.example.test:8080',
            'middlewares' => [static fn (callable $handler): MockHandler => $mock],
        ]);

        $response = $api->Finances()->salesReportDetails(
            new DateTimeImmutable('2026-11-15T00:00:00+03:00'),
            new DateTimeImmutable('2026-11-15T23:59:59+03:00'),
            30000,
            123,
            'daily',
        );

        $request = $mock->getLastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://finance-api.wildberries.ru/api/finance/v1/sales-reports/detailed', (string)$request->getUri());
        self::assertSame('fake-finance-key', $request->getHeaderLine('Authorization'));
        self::assertSame('http://proxy.example.test:8080', $mock->getLastOptions()['proxy']);
        self::assertSame([
            'dateFrom' => '2026-11-15T00:00:00+03:00',
            'dateTo' => '2026-11-15T23:59:59+03:00',
            'limit' => 30000,
            'rrdId' => 123,
            'period' => 'daily',
        ], json_decode((string)$request->getBody(), true, flags: JSON_THROW_ON_ERROR));
        self::assertSame(204, $response->statusCode);
    }

    public function testRateLimitErrorPreservesRetryHeader(): void
    {
        $mock = new MockHandler([new Response(429, ['X-Ratelimit-Retry' => '120'])]);
        $api = new API(['middlewares' => [static fn (callable $handler): MockHandler => $mock]]);
        $date = new DateTimeImmutable('2026-11-15');

        try {
            $api->Finances()->salesReportDetails($date, $date, 30000);
            self::fail('Ожидалось исключение HTTP 429');
        } catch (ApiClientException $exception) {
            self::assertSame(429, $exception->statusCode());
            self::assertSame(120, $exception->response()?->rateLimit->retry);
        }
    }
}
