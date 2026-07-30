<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Tests\Unit;

use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\API\Client;
use Dakword\WBSeller\API\Endpoint\Analytics;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class AnalyticsEndpointTest extends TestCase
{
    public function testSalesFunnelProductsUsesCurrentDefaultsAndOptionalPastPeriod(): void
    {
        [$analytics, $mock] = $this->analyticsWithMock();

        $analytics->v3SalesFunnelProducts(
            new \DateTime('2026-07-01'),
            new \DateTime('2026-07-07'),
        );

        $body = $this->lastJsonBody($mock);
        self::assertSame('/api/analytics/v3/sales-funnel/products', $mock->getLastRequest()->getUri()->getPath());
        self::assertSame(['start' => '2026-07-01', 'end' => '2026-07-07'], $body['selectedPeriod']);
        self::assertArrayNotHasKey('pastPeriod', $body);
        self::assertSame(1000, $body['limit']);

        $bodyWithPastPeriod = $this->salesFunnelWithOrderField($analytics, $mock);
        self::assertSame('orderCount', $bodyWithPastPeriod['orderBy']['field']);
        self::assertSame(
            ['start' => '2026-06-24', 'end' => '2026-06-30'],
            $bodyWithPastPeriod['pastPeriod'],
        );
    }

    public function testSalesFunnelHistoryMethodsBuildCompleteBodies(): void
    {
        [$analytics, $mock] = $this->analyticsWithMock(2);

        $analytics->v3SalesFunnelProductsHistory(
            new \DateTime('2026-07-01'),
            new \DateTime('2026-07-07'),
            [123],
            true,
            'week',
        );
        self::assertSame([
            'selectedPeriod' => ['start' => '2026-07-01', 'end' => '2026-07-07'],
            'nmIds' => [123],
            'skipDeletedNm' => true,
            'aggregationLevel' => 'week',
        ], $this->lastJsonBody($mock));

        $analytics->v3SalesFunnelGroupedHistory(
            new \DateTime('2026-07-01'),
            new \DateTime('2026-07-07'),
            ['Brand'],
            [10],
            [20],
            true,
        );
        self::assertSame('/api/analytics/v3/sales-funnel/grouped/history', $mock->getLastRequest()->getUri()->getPath());
        self::assertSame([
            'selectedPeriod' => ['start' => '2026-07-01', 'end' => '2026-07-07'],
            'brandNames' => ['Brand'],
            'subjectIds' => [10],
            'tagIds' => [20],
            'skipDeletedNm' => true,
            'aggregationLevel' => 'day',
        ], $this->lastJsonBody($mock));
    }

    public static function rawPostMethods(): array
    {
        return [
            'search report' => ['searchReport', '/api/v2/search-report/report'],
            'search groups' => ['searchReportGroups', '/api/v2/search-report/table/groups'],
            'search details' => ['searchReportDetails', '/api/v2/search-report/table/details'],
            'search texts' => ['searchReportProductSearchTexts', '/api/v2/search-report/product/search-texts'],
            'search orders' => ['searchReportProductOrders', '/api/v2/search-report/product/orders'],
            'WB warehouses' => ['stocksReportWbWarehouses', '/api/analytics/v1/stocks-report/wb-warehouses'],
            'stock groups' => ['stocksReportProductGroups', '/api/v2/stocks-report/products/groups'],
            'stock products' => ['stocksReportProducts', '/api/v2/stocks-report/products/products'],
            'stock sizes' => ['stocksReportProductSizes', '/api/v2/stocks-report/products/sizes'],
            'stock offices' => ['stocksReportOffices', '/api/v2/stocks-report/offices'],
            'item rating v2' => ['itemRating', '/api/analytics/v2/item-rating'],
            'item rating v1' => ['itemRatingV1', '/api/analytics/v1/item-rating'],
        ];
    }

    #[DataProvider('rawPostMethods')]
    public function testRawPostMethodsForwardEveryParameter(string $method, string $path): void
    {
        [$analytics, $mock] = $this->analyticsWithMock();
        $params = ['required' => 'value', 'optional' => ['nested' => true]];

        $analytics->{$method}($params);

        $request = $mock->getLastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame($path, $request->getUri()->getPath());
        self::assertSame($params, $this->lastJsonBody($mock));
    }

    public function testCsvReportLifecycleUsesSwaggerPathsAndParameters(): void
    {
        [$analytics, $mock] = $this->analyticsWithMock(4);

        $analytics->createAnalyticsReport(
            'report-id',
            'DETAIL_HISTORY_REPORT',
            ['nmIDs' => [123]],
            'Report name',
        );
        self::assertSame([
            'id' => 'report-id',
            'reportType' => 'DETAIL_HISTORY_REPORT',
            'params' => ['nmIDs' => [123]],
            'userReportName' => 'Report name',
        ], $this->lastJsonBody($mock));

        $analytics->getAnalyticsReports(['first', 'second']);
        $request = $mock->getLastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('filter%5BdownloadIds%5D=first&filter%5BdownloadIds%5D=second', $request->getUri()->getQuery());

        $analytics->retryAnalyticsReport('report-id');
        self::assertSame('/api/v2/nm-report/downloads/retry', $mock->getLastRequest()->getUri()->getPath());
        self::assertSame(['downloadId' => 'report-id'], $this->lastJsonBody($mock));

        $analytics->downloadAnalyticsReportFile('report/id');
        self::assertSame('/api/v2/nm-report/downloads/file/report%2Fid', $mock->getLastRequest()->getUri()->getPath());
    }

    public function testSalesFunnelRejectsSwaggerLimitViolations(): void
    {
        [$analytics] = $this->analyticsWithMock();

        $this->expectException(InvalidArgumentException::class);
        $analytics->v3SalesFunnelProductsHistory(
            new \DateTime('2026-07-01'),
            new \DateTime('2026-07-07'),
            [],
        );
    }

    private function salesFunnelWithOrderField(Analytics $analytics, MockHandler $mock): array
    {
        $analytics->v3SalesFunnelProducts(
            new \DateTime('2026-07-01'),
            new \DateTime('2026-07-07'),
            new \DateTime('2026-06-24'),
            new \DateTime('2026-06-30'),
            orderByField: 'orderCount',
        );

        return $this->lastJsonBody($mock);
    }

    /** @return array{Analytics, MockHandler} */
    private function analyticsWithMock(int $responseCount = 2): array
    {
        $analytics = new Analytics('https://example.test', 'fake-key');
        $mock = new MockHandler(array_fill(0, $responseCount, new Response(200, [], '{}')));
        $client = new Client(
            'https://example.test',
            'fake-key',
            null,
            true,
            HandlerStack::create($mock),
        );

        $property = new ReflectionProperty(AbstractEndpoint::class, 'Client');
        $property->setValue($analytics, $client);

        return [$analytics, $mock];
    }

    private function lastJsonBody(MockHandler $mock): array
    {
        return json_decode((string) $mock->getLastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
