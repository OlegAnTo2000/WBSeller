<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Response;

final readonly class RateLimit
{
    public function __construct(
        public int $limit = 0,
        public int $remaining = 0,
        public int $reset = 0,
        public int $retry = 0,
    ) {
    }

    /** @return array{limit: int, remaining: int, reset: int, retry: int} */
    public function toArray(): array
    {
        return [
            'limit' => $this->limit,
            'remaining' => $this->remaining,
            'reset' => $this->reset,
            'retry' => $this->retry,
        ];
    }
}
