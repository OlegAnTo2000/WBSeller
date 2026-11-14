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

final class AdvFinanceEndpointTest extends TestCase
{
    public function testAdvertsBudgetsUseBatchSwaggerRoute(): void
    {
        [$adv, $mock] = $this->advWithMock();

        $adv->Finances()->getAdvertsBudgets([1234567, 63453471, 1234567]);

        $request = $mock->getLastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/api/advert/v2/budget', $request->getUri()->getPath());
        self::assertSame(
            ['advertIds' => [1234567, 63453471]],
            json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testAdvertsBudgetsRejectEmptyList(): void
    {
        [$adv, $mock] = $this->advWithMock();

        $this->expectException(InvalidArgumentException::class);

        try {
            $adv->Finances()->getAdvertsBudgets([]);
        } finally {
            self::assertNull($mock->getLastRequest());
        }
    }

    public function testAdvertsBudgetsRejectMoreThanFiftyCampaigns(): void
    {
        [$adv, $mock] = $this->advWithMock();

        $this->expectException(InvalidArgumentException::class);

        try {
            $adv->Finances()->getAdvertsBudgets(range(1, 51));
        } finally {
            self::assertNull($mock->getLastRequest());
        }
    }

    /**
     * @return array{Adv, MockHandler}
     */
    private function advWithMock(): array
    {
        $adv = new Adv('https://example.test', 'fake-key');
        $mock = new MockHandler([new Response(200, [], '{"adverts":[]}')]);
        $client = new Client('https://example.test', 'fake-key', null, true, HandlerStack::create($mock));

        $property = new ReflectionProperty(AbstractEndpoint::class, 'Client');
        $property->setValue($adv, $client);

        return [$adv, $mock];
    }
}
