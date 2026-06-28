<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Tests\Unit;

use Dakword\WBSeller\API\Client;
use Dakword\WBSeller\Exception\ApiClientException;
use Dakword\WBSeller\Exception\ApiTransportException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class ObservabilityTest extends TestCase
{
    public function testMiddlewareOrderIsDeterministic(): void
    {
        $events = [];
        $client = $this->client(new Response(200, [], '{}'));
        $client->addMiddleware($this->recordingMiddleware('first', $events));
        $client->addMiddleware($this->recordingMiddleware('second', $events));

        $client->request('GET', '/test');

        self::assertSame(['first:before', 'second:before', 'second:after', 'first:after'], $events);
    }

    public function testListenerOrderAndErrorsDoNotInterruptRequest(): void
    {
        $events = [];
        $client = $this->client(new Response(200, [], '{}'));
        $client->onRequest(static function () use (&$events): void { $events[] = 'request:1'; });
        $client->onRequest(static function (): void { throw new RuntimeException('listener failed'); });
        $client->onRequest(static function () use (&$events): void { $events[] = 'request:2'; });
        $client->onResponse(static function () use (&$events): void { $events[] = 'response:1'; });
        $client->onResponse(static function () use (&$events): void { $events[] = 'response:2'; });

        $client->request('GET', '/test');

        self::assertSame(['request:1', 'request:2', 'response:1', 'response:2'], $events);
    }

    public function testErrorListenersRunInOrderAndCannotReplaceHttpException(): void
    {
        $events = [];
        $client = $this->client(new Response(500, [], 'failed'));
        $client->onError(static function () use (&$events): void { $events[] = 'error:1'; });
        $client->onError(static function (): void { throw new RuntimeException('listener failed'); });
        $client->onError(static function () use (&$events): void { $events[] = 'error:2'; });

        try {
            $client->request('GET', '/test');
            self::fail('Ожидалось HTTP-исключение');
        } catch (ApiClientException) {
            self::assertSame(['error:1', 'error:2'], $events);
        }
    }

    public function testMiddlewareErrorIsNormalized(): void
    {
        $client = $this->client(new Response(200, [], '{}'));
        $client->addMiddleware(static function (): callable {
            return static function (): never {
                throw new RuntimeException('middleware failed');
            };
        });

        try {
            $client->request('GET', '/test');
            self::fail('Ожидалось транспортное исключение');
        } catch (ApiTransportException $exception) {
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }
    }

    private function client(Response ...$responses): Client
    {
        return new Client(
            'https://example.test',
            'fake-key',
            null,
            true,
            HandlerStack::create(new MockHandler($responses)),
        );
    }

    private function recordingMiddleware(string $name, array &$events): callable
    {
        return static function (callable $handler) use ($name, &$events): callable {
            return static function (RequestInterface $request, array $options) use ($handler, $name, &$events): PromiseInterface {
                $events[] = $name . ':before';

                return $handler($request, $options)->then(
                    static function ($response) use ($name, &$events) {
                        $events[] = $name . ':after';
                        return $response;
                    },
                );
            };
        };
    }
}
