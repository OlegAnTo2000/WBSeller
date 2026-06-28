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
        $endpoint = $this->endpointWithResponses(
            new Response(504, [], '{"error":"timeout"}'),
            new Response(200, [], '{"ok":true}'),
        );

        $endpoint->retryOnTooManyRequests(2, 0);

        self::assertTrue($endpoint->readByPost()->ok);
    }

    public function testPostIsNotRetriedByDefault(): void
    {
        $endpoint = $this->endpointWithResponses(
            new Response(504, [], '{"error":"timeout"}'),
            new Response(200, [], '{"ok":true}'),
        );

        $this->expectException(ApiClientException::class);
        $endpoint->retryOnTooManyRequests(2, 0);
        $endpoint->writeByPost();
    }

    public function testNonRetryableAttributeDisablesGetRetry(): void
    {
        $endpoint = $this->endpointWithResponses(
            new Response(504, [], '{"error":"timeout"}'),
            new Response(200, [], '{"ok":true}'),
        );

        $this->expectException(ApiClientException::class);
        $endpoint->retryOnTooManyRequests(2, 0);
        $endpoint->unsafeGet();
    }

    private function endpointWithResponses(Response ...$responses): RetryPolicyEndpoint
    {
        $endpoint = new RetryPolicyEndpoint('https://example.test', 'fake-key');
        $client = new Client(
            'https://example.test',
            'fake-key',
            null,
            true,
            HandlerStack::create(new MockHandler($responses)),
        );

        $property = new ReflectionProperty(AbstractEndpoint::class, 'Client');
        $property->setValue($endpoint, $client);

        return $endpoint;
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
}
