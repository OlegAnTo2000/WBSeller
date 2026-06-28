<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Exception;

use Dakword\WBSeller\API\Response\ApiResponse;
use Throwable;

/**
 * Исключение HTTP-ошибки от WB API.
 *
 * Бросается при любом HTTP-ответе 4xx/5xx независимо от формата тела.
 *
 * Код исключения (`getCode()`) содержит HTTP-статус ответа.
 * Сообщение (`getMessage()`) содержит текст ошибки из тела ответа WB или HTTP reason phrase.
 * Декодированное тело, исходная строка и заголовки доступны отдельными методами.
 * Исходное Guzzle-исключение доступно через `getPrevious()`.
 *
 * Подводный камень: при 429 с сообщением "Технический перерыв" бросается
 * ApiTimeRestrictionsException, а не этот класс.
 */
class ApiClientException extends WBSellerException
{
    public function __construct(
        string $message,
        int $statusCode = 0,
        ?Throwable $previous = null,
        private readonly ?ApiResponse $response = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int
    {
        return $this->getCode();
    }

    public function responseBody(): mixed
    {
        return $this->response?->body;
    }

    public function rawResponse(): ?string
    {
        return $this->response?->rawBody;
    }

    public function responseHeaders(): array
    {
        return $this->response?->headers ?? [];
    }

    public function response(): ?ApiResponse
    {
        return $this->response;
    }
}
