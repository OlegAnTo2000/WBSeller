<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Enum;

/**
 * Источник средств при пополнении бюджета рекламной кампании.
 *
 * Используется в методах пополнения бюджета (AdvFinance endpoint):
 * ACCOUNT — основной счёт продавца,
 * BALANCE — бонусный баланс WB,
 * BONUSES — промо-бонусы.
 *
 * @see \Dakword\WBSeller\API\Endpoint\Subpoint\AdvFinance
 */
enum AdvertDepositSource: int
{
    /** Основной счёт продавца. */
    case ACCOUNT = 0;

    /** Бонусный баланс WB. */
    case BALANCE = 1;

    /** Промо-бонусы. */
    case BONUSES = 2;

    /**
     * @deprecated Используйте self::cases() или array_column(self::cases(), 'value').
     * @return int[]
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
