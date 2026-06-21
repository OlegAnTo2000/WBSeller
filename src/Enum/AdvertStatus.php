<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Enum;

/**
 * Статус рекламной кампании WB (поисковой и автоматической).
 *
 * Используется при фильтрации списка кампаний и в ответах API.
 * Не путать с MediaAdvertStatus — он описывает статусы медиакампаний.
 *
 * Жизненный цикл кампании:
 *   READY (4) → PLAY (9) ↔ PAUSE (11) → DONE (7)
 *
 * @see MediaAdvertStatus  Статусы медиакампаний
 * @see \Dakword\WBSeller\API\Endpoint\Adv
 */
enum AdvertStatus: int
{
    /** Рекламная кампания в процессе удаления. */
    case DELETED   = -1;

    /** Рекламная кампания готова к запуску. */
    case READY     = 4;

    /** Рекламная кампания завершена. */
    case DONE      = 7;

    /** Отказался (кампания отклонена). */
    case CANCELLED = 8;

    /** Идут показы. */
    case PLAY      = 9;

    /** Рекламная кампания на паузе. */
    case PAUSE     = 11;

    /**
     * Возвращает массив всех допустимых значений статуса (int).
     *
     * @deprecated Используйте self::cases() для получения enum-кейсов
     *             или array_column(self::cases(), 'value') для скалярных значений.
     * @return int[]
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
