<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Tests\Unit;

use Dakword\WBSeller\API;
use Dakword\WBSeller\Exception\ApiClientException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public static function invalidRetryParameters(): array
    {
        return [
            'zero attempts' => [0, 1],
            'negative attempts' => [-1, 1],
            'negative delay' => [1, -1],
        ];
    }

    #[DataProvider('invalidRetryParameters')]
    public function testInvalidRetryParametersAreRejected(int $attempts, int $delay): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new API(['masterkey' => 'fake-key']))
            ->Test()
            ->retryOnTooManyRequests($attempts, $delay);
    }

    public function testMalformedJwtIsRejectedBeforeRequest(): void
    {
        $this->expectException(ApiClientException::class);
        $this->expectExceptionCode(401);

        (new API(['masterkey' => 'invalid.invalid.invalid']))->Test();
    }

    public function testNonCallableMiddlewareIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new API(['middlewares' => ['not-callable']]);
    }

    public function testValidMiddlewareIsAccepted(): void
    {
        $middleware = static fn(callable $handler): callable => $handler;

        $api = new API(['middlewares' => [$middleware]]);

        self::assertInstanceOf(API::class, $api);
    }

    public function testNonCallableListenerIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new API(['listeners' => ['request' => ['not-callable']]]);
    }

    public function testUnknownListenerTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new API(['listeners' => ['unknown' => [static fn() => null]]]);
    }

    public function testValidListenersAreAccepted(): void
    {
        $listener = static fn(array $event) => null;
        $api = new API([
            'listeners' => [
                'request' => [$listener],
                'response' => [$listener],
                'error' => [$listener],
            ],
        ]);

        self::assertSame([$listener], $api->getListeners()['request']);
    }
}
