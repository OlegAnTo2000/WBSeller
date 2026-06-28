<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Tests\Unit;

use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\API\Attribute\NonRetryable;
use Dakword\WBSeller\API\Attribute\Retryable;
use Dakword\WBSeller\API\Client;
use Dakword\WBSeller\Exception\ApiClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class RetryPolicyTest extends TestCase
{
    public function testRetryableAttributeAllowsPostRetry(): void
    {
        [$endpoint, $mock] = $this->endpointWithResponses(
            new Response(504, [], '{"error":"timeout"}'),
            new Response(200, [], '{"ok":true}'),
        );

        $endpoint->retryOnTooManyRequests(2, 0);

        self::assertTrue($endpoint->readByPost()->ok);
        self::assertCount(0, $mock);
    }

    public function testPostIsNotRetriedByDefault(): void
    {
        [$endpoint, $mock] = $this->endpointWithResponses(
            new Response(504, [], '{"error":"timeout"}'),
            new Response(200, [], '{"ok":true}'),
        );

        $endpoint->retryOnTooManyRequests(2, 0);
        try {
            $endpoint->writeByPost();
            self::fail('Ожидалось HTTP-исключение');
        } catch (ApiClientException) {
            self::assertCount(1, $mock);
        }
    }

    public function testNonRetryableAttributeDisablesGetRetry(): void
    {
        [$endpoint, $mock] = $this->endpointWithResponses(
            new Response(504, [], '{"error":"timeout"}'),
            new Response(200, [], '{"ok":true}'),
        );

        $endpoint->retryOnTooManyRequests(2, 0);
        try {
            $endpoint->unsafeGet();
            self::fail('Ожидалось HTTP-исключение');
        } catch (ApiClientException) {
            self::assertCount(1, $mock);
        }
    }

    public function testRetryIsDisabledByDefault(): void
    {
        [$endpoint, $mock] = $this->endpointWithResponses(
            new Response(504, [], '{"error":"timeout"}'),
            new Response(200, [], '{"ok":true}'),
        );

        try {
            $endpoint->readByGet();
            self::fail('Ожидалось HTTP-исключение');
        } catch (ApiClientException) {
            self::assertCount(1, $mock);
        }
    }

    /** @return array{RetryPolicyEndpoint, MockHandler} */
    private function endpointWithResponses(Response ...$responses): array
    {
        $endpoint = new RetryPolicyEndpoint('https://example.test', 'fake-key');
        $mock = new MockHandler($responses);
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
}

final class RetryPolicyEndpoint extends AbstractEndpoint
{
    #[Retryable]
    public function readByPost(): object
    {
        return $this->postRequest('/read');
    }

    public function writeByPost(): object
    {
        return $this->postRequest('/write');
    }

    #[NonRetryable]
    public function unsafeGet(): object
    {
        return $this->getRequest('/unsafe');
    }

    public function readByGet(): object
    {
        return $this->getRequest('/read');
    }
}
