<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Tests\Unit;

use Dakword\WBSeller\API;
use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\API\Client;
use Dakword\WBSeller\API\Endpoint\Common;
use Dakword\WBSeller\API\Endpoint\Feedbacks;
use Dakword\WBSeller\API\Endpoint\Users;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class GeneralEndpointTest extends TestCase
{
    public function testCommonOperationsUseSwaggerRoutesAndParameters(): void
    {
        [$common, $mock] = $this->endpointWithMock(new Common('https://example.test', 'fake-key'), 4);

        $common->ping();
        self::assertSame('/ping', $mock->getLastRequest()->getUri()->getPath());

        $common->sellerInfo();
        self::assertSame('/api/v1/seller-info', $mock->getLastRequest()->getUri()->getPath());

        $common->subscriptions();
        self::assertSame('/api/common/v1/subscriptions', $mock->getLastRequest()->getUri()->getPath());

        $common->tariffConstructorOptions('en');
        self::assertSame('/api/common/v1/tariff-constructor/options', $mock->getLastRequest()->getUri()->getPath());
        self::assertSame('locale=en', $mock->getLastRequest()->getUri()->getQuery());
    }

    public function testCurrentNewsMethodSupportsAllSwaggerParameters(): void
    {
        [$common, $mock] = $this->endpointWithMock(new Common('https://example.test', 'fake-key'));

        $common->News()->list(new \DateTimeImmutable('2026-07-01'), 7369);

        $request = $mock->getLastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/api/communications/v2/news', $request->getUri()->getPath());
        self::assertSame('from=2026-07-01&fromID=7369', $request->getUri()->getQuery());
    }

    public function testSellerRatingUsesFeedbacksApiRoute(): void
    {
        [$feedbacks, $mock] = $this->endpointWithMock(new Feedbacks('https://example.test', 'fake-key'));

        $feedbacks->sellerRating();

        self::assertSame('/api/common/v1/rating', $mock->getLastRequest()->getUri()->getPath());
    }

    public function testUsersOperationsUseSwaggerMethodsAndCompleteParameters(): void
    {
        [$users, $mock] = $this->endpointWithMock(new Users('https://example.test', 'fake-key'), 4);
        $access = [['code' => 'balance', 'disabled' => false]];

        $users->invite(['phoneNumber' => '79990000000', 'position' => 'Manager'], $access);
        self::assertSame('POST', $mock->getLastRequest()->getMethod());
        self::assertSame('/api/v1/invite', $mock->getLastRequest()->getUri()->getPath());
        self::assertSame([
            'invite' => ['phoneNumber' => '79990000000', 'position' => 'Manager'],
            'access' => $access,
        ], $this->lastJsonBody($mock));

        $users->list(50, 10, true);
        self::assertSame('GET', $mock->getLastRequest()->getMethod());
        self::assertSame('limit=50&offset=10&isInviteOnly=1', $mock->getLastRequest()->getUri()->getQuery());

        $users->updateAccess([['userId' => 42, 'access' => $access]]);
        self::assertSame('PUT', $mock->getLastRequest()->getMethod());
        self::assertSame([
            'usersAccesses' => [['userId' => 42, 'access' => $access]],
        ], $this->lastJsonBody($mock));

        $users->delete(42);
        self::assertSame('DELETE', $mock->getLastRequest()->getMethod());
        self::assertSame('/api/v1/user', $mock->getLastRequest()->getUri()->getPath());
        self::assertSame('deletedUserID=42', $mock->getLastRequest()->getUri()->getQuery());
    }

    public function testUsersFactoryIsAvailable(): void
    {
        self::assertInstanceOf(Users::class, (new API(['masterkey' => 'fake-key']))->Users());
    }

    public function testNewsRequiresAtLeastOneFilter(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Common('https://example.test', 'fake-key'))->News()->list();
    }

    public function testTariffConstructorRejectsUnknownLocale(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Common('https://example.test', 'fake-key'))->tariffConstructorOptions('fr');
    }

    public function testUsersLimitMatchesSwaggerMaximum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Users('https://example.test', 'fake-key'))->list(101);
    }

    /**
     * @template T of AbstractEndpoint
     * @param T $endpoint
     * @return array{0: T, 1: MockHandler}
     */
    private function endpointWithMock(AbstractEndpoint $endpoint, int $responseCount = 1): array
    {
        $mock = new MockHandler(array_fill(0, $responseCount, new Response(200, [], '{}')));
        $client = new Client(
            'https://example.test',
            'fake-key',
            null,
            true,
            HandlerStack::create($mock),
        );

        $property = new ReflectionProperty(AbstractEndpoint::class, 'Client');
        $property->setValue($endpoint, $client);

        return [$endpoint, $mock];
    }

    private function lastJsonBody(MockHandler $mock): array
    {
        return json_decode((string) $mock->getLastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
