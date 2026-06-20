<?php

declare(strict_types=1);

namespace Dakword\WBSeller;

use Dakword\WBSeller\Exception\WBSellerException;
use DateTime;

class APIToken
{
    const TYPE_BASIC    = 1;
    const TYPE_TEST     = 2;
    const TYPE_PERSONAL = 3;
    const TYPE_SERVICE  = 4;

    const BIT = [
        1  => 'Контент',
        2  => 'Аналитика',
        3  => 'Цены и скидки',
        4  => 'Маркетплейс',
        5  => 'Статистика',
        6  => 'Продвижение',
        7  => 'Вопросы и отзывы',
        8  => 'Рекомендации',
        9  => 'Чат с покупателями',
        10 => 'Поставки',
        11 => 'Возвраты покупателями',
        12 => 'Документы',
        13 => 'Финансы',
        16 => 'Пользователи',
    ];
    const BIT_READONLY = 30;

    private array $apiFlagPosition = [
        'common'      => 0,
        'tariffs'     => 0,
        'content'     => 1,
        'analytics'   => 2,
        'calendar'    => 3,
        'prices'      => 3,
        'marketplace' => 4,
        'statistics'  => 5,
        'adv'         => 6,
        'feedbacks'   => 7,
        'questions'   => 7,
        'recommends'  => 8,
        'chat'        => 9,
        'supplies'    => 10,
        'returns'     => 11,
        'documents'   => 12,
        'finances'    => 13,
        'users'       => 16,
    ];
    private string $token;
    private ?object $payload;

    function __construct(string $token)
    {
        $this->token = $token;

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new WBSellerException('Неверный формат токена');
        }
        $this->payload = json_decode(base64_decode($parts[1]));

        if (json_last_error() != JSON_ERROR_NONE) {
            throw new WBSellerException('Неверный формат токена');
        }

        foreach (['exp', 's', 'sid', 'acc', 't'] as $param) {
            if (!property_exists($this->payload, $param)) {
                throw new WBSellerException('Неверный формат токена');
            }
        }
    }

    public function __toString(): string
    {
        return $this->token;
    }

    public function getPayload(): object
    {
        return $this->payload;
    }

    public function expireDate(): DateTime
    {
        return (new DateTime())->setTimestamp($this->payload->exp);
    }

    public function isExpired(): bool
    {
        return (new DateTime()) > $this->expireDate();
    }

    public function daysUntilExpiry(): int
    {
        return (int) floor(($this->payload->exp - time()) / 86400);
    }

    public function hoursUntilExpiry(): int
    {
        return (int) floor(($this->payload->exp - time()) / 3600);
    }

    public function masked(): string
    {
        return substr($this->token, 0, 5) . '...' . substr($this->token, -5);
    }

    public function tokenType(): int
    {
        return $this->payload->acc;
    }

    public function isBasic(): bool
    {
        return $this->payload->acc === self::TYPE_BASIC;
    }

    public function isTest(): bool
    {
        return $this->payload->acc === self::TYPE_TEST;
    }

    public function isPersonal(): bool
    {
        return $this->payload->acc === self::TYPE_PERSONAL;
    }

    public function isService(): bool
    {
        return $this->payload->acc === self::TYPE_SERVICE;
    }

    public function serviceId(): ?string
    {
        if (!$this->isService()) {
            return null;
        }
        $for = $this->payload->for ?? '';
        return str_starts_with($for, 'asid:') ? substr($for, 5) : null;
    }

    public function isReadOnly(): bool
    {
        return $this->isFlagSet(self::BIT_READONLY);
    }

    public function sellerId(): ?int
    {
        return $this->payload->oid ?? null;
    }

    public function sellerUUID(): string
    {
        return $this->payload->sid;
    }

    public function accessList(): array
    {
        return array_filter(self::BIT, fn($position) => $this->isFlagSet($position), ARRAY_FILTER_USE_KEY);
    }

    public function accessTo(string $apiName): bool
    {
        $position = $this->apiFlagPosition[$apiName] ?? null;
        if (is_null($position)) {
            return false;
        }
        if ($position) {
            return $this->isFlagSet($position);
        }
        return true;
    }

    public function hasAccess(string ...$apiNames): bool
    {
        foreach ($apiNames as $apiName) {
            if (!$this->accessTo($apiName)) {
                return false;
            }
        }
        return true;
    }

    private function isFlagSet($position): bool
    {
        return (bool) ($this->payload->s & (0b1 << $position));
    }
}