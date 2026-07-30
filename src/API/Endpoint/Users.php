<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint;

use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\API\Response\ApiResponse;
use InvalidArgumentException;

class Users extends AbstractEndpoint
{
    protected string $apiName = 'users';

    /**
     * Пригласить пользователя в профиль продавца.
     *
     * @param array{phoneNumber: string, position?: string} $invite Данные приглашения
     * @param array<int, array{code: string, disabled: bool}>|null $access Права пользователя
     */
    public function invite(array $invite, ?array $access = null): ApiResponse
    {
        if (!isset($invite['phoneNumber']) || !is_string($invite['phoneNumber']) || $invite['phoneNumber'] === '') {
            throw new InvalidArgumentException('Необходимо указать номер телефона приглашаемого пользователя');
        }
        if (isset($invite['position']) && mb_strlen($invite['position']) > 150) {
            throw new InvalidArgumentException('Должность пользователя не может быть длиннее 150 символов');
        }

        return $this->postRequest('/api/v1/invite', [
            'invite' => $invite,
        ] + ($access !== null ? ['access' => $access] : []));
    }

    /**
     * Получить активных или приглашённых пользователей продавца.
     */
    public function list(int $limit = 100, int $offset = 0, bool $isInviteOnly = false): ApiResponse
    {
        if ($limit > 100) {
            throw new InvalidArgumentException('Количество пользователей не может превышать 100');
        }

        return $this->getRequest('/api/v1/users', [
            'limit' => $limit,
            'offset' => $offset,
            'isInviteOnly' => $isInviteOnly,
        ]);
    }

    /**
     * Изменить права пользователей продавца.
     *
     * @param array<int, array{userId?: int, access?: array<int, array{code: string, disabled: bool}>}> $usersAccesses
     */
    public function updateAccess(array $usersAccesses): ApiResponse
    {
        return $this->putRequest('/api/v1/users/access', [
            'usersAccesses' => $usersAccesses,
        ]);
    }

    /**
     * Закрыть пользователю доступ к профилю продавца.
     */
    public function delete(int $deletedUserId): ApiResponse
    {
        return $this->deleteRequest('/api/v1/user?deletedUserID=' . rawurlencode((string) $deletedUserId));
    }
}
