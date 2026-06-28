<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Exception;

use JsonException;

final class ApiResponseDecodingException extends WBSellerException
{
    public function __construct(JsonException $previous)
    {
        parent::__construct('Не удалось декодировать JSON ответа: ' . $previous->getMessage(), 0, $previous);
    }
}
