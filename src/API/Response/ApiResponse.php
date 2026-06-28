<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Response;

use Dakword\WBSeller\Exception\ApiResponseDecodingException;
use JsonException;

final readonly class ApiResponse
{
    public function __construct(
        private string $body,
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

    public function text(): string
    {
        return $this->body;
    }

    public function json(bool $associative = false): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        try {
            return json_decode($this->body, $associative, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ApiResponseDecodingException($exception);
        }
    }

    public function isEmpty(): bool
    {
        return $this->body === '';
    }

    public function header(string $name): array
    {
        foreach ($this->headers as $headerName => $values) {
            if (strcasecmp($headerName, $name) === 0) {
                return $values;
            }
        }

        return [];
    }

    public function headerLine(string $name): string
    {
        return implode(', ', $this->header($name));
    }
}
