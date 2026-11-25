<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Tests\Unit;

use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\API\Client;
use Dakword\WBSeller\API\Endpoint\Adv;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class AdvCampaignListEndpointTest extends TestCase
{
    public function testV3SendsFiltersAndCursorAsQueryParameters(): void
    {
        [$adv, $mock] = $this->advWithMock();

        $adv->apiAdvertV3Adverts(statuses: [9, 11], paymentType: 'cpm', limit: 25, next: 'abc+def=/');

        $request = $mock->getLastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/api/advert/v3/adverts', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $params);
        self::assertSame(['statuses' => '9,11', 'paymentType' => 'cpm', 'limit' => '25', 'next' => 'abc+def=/'], $params);
    }

    public function testV3CanRequestTheFirstPageWithoutACursor(): void
    {
        [$adv, $mock] = $this->advWithMock();

        $adv->apiAdvertV3Adverts(next: '');

        self::assertSame('', $mock->getLastRequest()->getUri()->getQuery());
    }

    public function testV3CanRequestOnlySelectedCampaigns(): void
    {
        [$adv, $mock] = $this->advWithMock();

        $adv->apiAdvertV3Adverts(advertIds: [12345, 23456]);

        parse_str($mock->getLastRequest()->getUri()->getQuery(), $params);
        self::assertSame(['advertIds' => '12345,23456'], $params);
    }

    public function testV3RejectsIdsCombinedWithPagination(): void
    {
        [$adv, $mock] = $this->advWithMock();
        $this->expectException(InvalidArgumentException::class);

        try {
            $adv->apiAdvertV3Adverts(advertIds: [12345], limit: 25);
        } finally {
            self::assertNull($mock->getLastRequest());
        }
    }

    public function testV3RejectsLimitOutsideWbRange(): void
    {
        [$adv, $mock] = $this->advWithMock();
        $this->expectException(InvalidArgumentException::class);

        try {
            $adv->apiAdvertV3Adverts(limit: 51);
        } finally {
            self::assertNull($mock->getLastRequest());
        }
    }

    /** @return array{Adv, MockHandler} */
    private function advWithMock(): array
    {
        $adv = new Adv('https://example.test', 'fake-key');
        $mock = new MockHandler([new Response(200, [], '{"adverts":[],"next":""}')]);
        $client = new Client('https://example.test', 'fake-key', null, true, HandlerStack::create($mock));

        $property = new ReflectionProperty(AbstractEndpoint::class, 'Client');
        $property->setValue($adv, $client);

        return [$adv, $mock];
    }
}
