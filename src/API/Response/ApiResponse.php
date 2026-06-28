<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Response;

final readonly class ApiResponse
{
    public function __construct(
        public mixed $body,
        public string $rawBody,
        public int $statusCode,
        public string $reasonPhrase,
        public array $headers,
        public RateLimit $rateLimit,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
