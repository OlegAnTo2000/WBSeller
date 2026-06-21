<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Enum;

/**
 * Статус медиарекламной кампании WB (баннеры, видео и т.д.).
 *
 * Медиакампании проходят модерацию (DRAFT → MODERATION → ACCEPTED/REJECTED)
 * перед началом показов, в отличие от обычных кампаний.
 * Кампания может быть на паузе по трём причинам: вручную продавцом (PAUSED),
 * по дневному лимиту (PAUSED_BY_LIMIT) или по исчерпанию бюджета (PAUSED_BY_BUDGET).
 *
 * @see AdvertStatus  Статусы обычных (поисковых/автоматических) кампаний
 * @see \Dakword\WBSeller\API\Endpoint\Subpoint\AdvMedia
 */
enum MediaAdvertStatus: int
{
    /** Черновик — кампания ещё не отправлена на модерацию. */
    case DRAFT           = 1;

    /** На модерации. */
    case MODERATION      = 2;

    /** Отклонена (с возможностью вернуть на модерацию). */
    case REJECTED        = 3;

    /** Одобрена модератором. */
    case ACCEPTED        = 4;

    /** Запланирована, показы ещё не начались. */
    case PLANNED         = 5;

    /** Идут показы. */
    case PLAYED          = 6;

    /** Кампания завершена. */
    case COMPLETED       = 7;

    /** Отказался (кампания отменена продавцом). */
    case CANCELLED       = 8;

    /** Приостановлена продавцом вручную. */
    case PAUSED          = 9;

    /** Автопауза по достижению дневного лимита бюджета. */
    case PAUSED_BY_LIMIT  = 10;

    /** Автопауза по полному расходу бюджета кампании. */
    case PAUSED_BY_BUDGET = 11;

    /**
     * @deprecated Используйте self::cases() или array_column(self::cases(), 'value').
     * @return int[]
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
