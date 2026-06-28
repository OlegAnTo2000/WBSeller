<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Tests\Unit;

use Dakword\WBSeller\API\Client;
use Dakword\WBSeller\Enum\HttpMethod;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Exception\ClientException;
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

        self::assertEquals($expected, $client->request(HttpMethod::GET, '/test'));
        self::assertEquals($expected, $client->response);
    }

    public function testMockHandlerDoesNotRequireNetworkOrRealApiKey(): void
    {
        $client = $this->clientWithResponses(new Response(200, [], '{"ok":true}'));

        self::assertTrue($client->request('GET', '/test')->ok);
        self::assertSame(200, $client->responseCode);
    }

    public function testEmptyErrorBodyIsStoredAsNull(): void
    {
        $client = $this->clientWithResponses(new Response(400, [], ''));

        try {
            $client->request(HttpMethod::GET, '/test');
            self::fail('Ожидалось HTTP-исключение');
        } catch (ClientException) {
            self::assertNull($client->response);
            self::assertSame('', $client->rawResponse);
        }
    }

    private function clientWithResponses(Response ...$responses): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));

        return new Client('https://example.test', 'fake-key', null, true, $stack);
    }
}
