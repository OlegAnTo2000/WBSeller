<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Tests\Unit;

use Dakword\WBSeller\API\Client;
use Dakword\WBSeller\Enum\HttpMethod;
use Dakword\WBSeller\Exception\ApiClientException;
use Dakword\WBSeller\Exception\ApiTransportException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public static function responseBodies(): array
    {
        return [
            'object' => ['{"status":"ok"}', (object) ['status' => 'ok']],
            'array' => ['[1,2,3]', [1, 2, 3]],
            'string scalar' => ['"ok"', 'ok'],
            'integer scalar' => ['42', 42],
            'boolean scalar' => ['true', true],
            'JSON null' => ['null', null],
            'empty body' => ['', null],
            'invalid JSON' => ['not-json', 'not-json'],
        ];
    }

    #[DataProvider('responseBodies')]
    public function testResponseBodyIsDecodedConsistently(string $body, mixed $expected): void
    {
        $client = $this->clientWithResponses(new Response(200, [], $body));

        $response = $client->request(HttpMethod::GET, '/test');

        self::assertEquals($expected, $response->body);
        self::assertSame($body, $response->rawBody);
    }

    public function testMockHandlerDoesNotRequireNetworkOrRealApiKey(): void
    {
        $client = $this->clientWithResponses(new Response(200, [], '{"ok":true}'));

        $response = $client->request('GET', '/test');

        self::assertTrue($response->body->ok);
        self::assertSame(200, $response->statusCode);
    }

    public static function errorResponses(): array
    {
        $cases = [];
        foreach ([400, 401, 429, 500, 504] as $status) {
            $cases["{$status} JSON"] = [$status, '{"message":"failure"}', (object) ['message' => 'failure'], 'failure'];
            $cases["{$status} text"] = [$status, 'plain failure', 'plain failure', 'plain failure'];
            $cases["{$status} empty"] = [$status, '', null, (new Response($status))->getReasonPhrase()];
        }

        return $cases;
    }

    #[DataProvider('errorResponses')]
    public function testHttpErrorsHaveSamePackageContract(
        int $status,
        string $body,
        mixed $decoded,
        string $message,
    ): void {
        $client = $this->clientWithResponses(new Response($status, ['Content-Type' => 'text/plain'], $body));

        try {
            $client->request(HttpMethod::GET, '/test');
            self::fail('Ожидалось HTTP-исключение');
        } catch (ApiClientException $exception) {
            self::assertSame($status, $exception->statusCode());
            self::assertEquals($decoded, $exception->responseBody());
            self::assertSame($body, $exception->rawResponse());
            self::assertSame($message, $exception->getMessage());
            self::assertNotNull($exception->getPrevious());
            self::assertEquals($decoded, $exception->response()?->body);
        }
    }

    public function testResponseContainsHeadersAndRateLimit(): void
    {
        $client = $this->clientWithResponses(new Response(200, [
            'X-Ratelimit-Limit' => '10',
            'X-Ratelimit-Remaining' => '7',
            'X-Ratelimit-Reset' => '29',
            'X-Ratelimit-Retry' => '2',
        ], '{}'));

        $response = $client->request('GET', '/test');

        self::assertSame('10', $response->headers['X-Ratelimit-Limit'][0]);
        self::assertSame(10, $response->rateLimit->limit);
        self::assertSame(7, $response->rateLimit->remaining);
        self::assertSame(29, $response->rateLimit->reset);
        self::assertSame(2, $response->rateLimit->retry);
    }

    public function testTransportErrorIsNormalized(): void
    {
        $request = new Request('GET', 'https://example.test/test');
        $client = $this->clientWithResponses(new ConnectException('DNS failed', $request));

        try {
            $client->request(HttpMethod::GET, '/test');
            self::fail('Ожидалось транспортное исключение');
        } catch (ApiTransportException $exception) {
            self::assertInstanceOf(ConnectException::class, $exception->getPrevious());
            self::assertSame('DNS failed', $exception->getMessage());
        }
    }

    private function clientWithResponses(Response|\Throwable ...$responses): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));

        return new Client('https://example.test', 'fake-key', null, true, $stack);
    }
}
