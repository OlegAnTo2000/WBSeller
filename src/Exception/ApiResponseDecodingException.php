<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Exception;

use Dakword\WBSeller\API\Response\ApiResponse;
use JsonException;

final class ApiResponseDecodingException extends WBSellerException
{
    public function __construct(JsonException $previous, ?ApiResponse $response = null)
    {
        $message = 'Не удалось декодировать JSON ответа: ' . $previous->getMessage();

        if ($response !== null) {
            $body = $response->text();
            // Маскируем секреты до обрезки, чтобы граница фрагмента не раскрыла часть значения.
            $preview = preg_replace([
                '/\bBearer\s+[^\s"<>]+/i',
                '/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/',
                '/(["\']?(?:authorization|access_token|refresh_token|token|api[_-]?key|password|secret)["\']?\s*[:=]\s*)(?:"[^"\r\n]*"|\'[^\'\r\n]*\'|[^\s,;<>]+)/i',
            ], '[REDACTED]', $body) ?? '[unavailable]';
            $preview = substr($preview, 0, 2000);

            $message .= sprintf(
                '; HTTP %d; Content-Type: %s; тело: %d байт; фрагмент (до 2000 байт): %s',
                $response->statusCode,
                json_encode($response->headerLine('Content-Type'), JSON_INVALID_UTF8_SUBSTITUTE),
                strlen($body),
                json_encode($preview, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
        }

        parent::__construct($message, 0, $previous);
    }
}
