<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Exception;

/**
 * Локальная проверка claims токена завершилась ошибкой до отправки запроса.
 * Наследование от ApiClientException сохраняет совместимость существующих catch.
 */
class LocalTokenValidationException extends ApiClientException
{
}
