<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Auth;

use Dakword\WBSeller\APIToken;
use Dakword\WBSeller\Enum\ApiName;
use Dakword\WBSeller\Exception\LocalTokenValidationException;
use Dakword\WBSeller\Exception\WBSellerException;

final class TokenClaimsValidator
{
    /**
     * Проверяет только доступные локально claims: формат, exp и маску доступа.
     * Подпись, отзыв токена и фактическую авторизацию проверяет только сервер WB.
     */
    public function validate(string $key, string $apiName = ''): void
    {
        if (substr_count($key, '.') !== 2) {
            return;
        }

        try {
            $token = new APIToken($key);
        } catch (WBSellerException $exception) {
            throw new LocalTokenValidationException('Неверный формат API-токена', 401, $exception);
        }

        if ($token->isExpired()) {
            throw new LocalTokenValidationException(
                sprintf('API токен истёк %s', $token->expireDate()->format('d.m.Y H:i')),
                401,
            );
        }

        $name = $apiName !== '' ? ApiName::tryFrom($apiName) : null;
        if ($apiName !== '' && ($name === null || !$token->accessTo($name))) {
            throw new LocalTokenValidationException(
                sprintf('Токен не имеет доступа к API "%s"', $apiName),
                403,
            );
        }
    }
}
