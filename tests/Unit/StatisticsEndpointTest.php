<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Tests\Unit;

use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\API\Client;
use Dakword\WBSeller\API\Endpoint\Statistics;
use Dakword\WBSeller\Tests\TestCase;
use DateTime;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use ReflectionProperty;

class StatisticsEndpointTest extends TestCase
{
    public function testDetailReportSendsPeriod(): void
    {
        [$statistics, $mock] = $this->statisticsWithMock();

        $statistics->detailReport(
            new DateTime('2026-07-27'),
            new DateTime('2026-07-28'),
            100,
            123,
            'daily',
        );

        parse_str($mock->getLastRequest()->getUri()->getQuery(), $query);

        self::assertSame('/api/v5/supplier/reportDetailByPeriod', $mock->getLastRequest()->getUri()->getPath());
        self::assertSame('100', $query['limit']);
        self::assertSame('123', $query['rrdid']);
        self::assertSame('daily', $query['period']);
    }

    public function testDetailReportRejectsInvalidPeriod(): void
    {
        [$statistics] = $this->statisticsWithMock();

        $this->expectException(InvalidArgumentException::class);

        $statistics->detailReport(new DateTime('2026-07-27'), new DateTime('2026-07-28'), 100, 0, 'monthly');
    }

    /**
     * @return array{0: Statistics, 1: MockHandler}
     */
    private function statisticsWithMock(): array
    {
        $statistics = new Statistics('https://example.test', 'fake-key');
        $mock = new MockHandler([new Response(200, [], '[]')]);
        $client = new Client(
            'https://example.test',
            'fake-key',
            null,
            true,
            HandlerStack::create($mock),
        );

        $property = new ReflectionProperty(AbstractEndpoint::class, 'Client');
        $property->setValue($statistics, $client);

        return [$statistics, $mock];
    }
}
