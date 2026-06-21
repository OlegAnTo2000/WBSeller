<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Enum;

/**
 * Тип рекламной кампании WB.
 *
 * Типы ON_CATALOG, ON_CARD, ON_SEARCH, ON_HOME_RECOM устарели:
 * WB более не создаёт кампании этих типов, но они могут встречаться
 * в исторических данных и ответах API.
 * Актуальные типы для новых кампаний: AUTO (8) и ON_SEARCH_CATALOG (9).
 *
 * Используется при фильтрации кампаний в Adv endpoint:
 * ```php
 * $api->Adv()->campaignsList(status: AdvertStatus::PLAY, type: AdvertType::AUTO);
 * ```
 *
 * @see AdvertStatus
 * @see \Dakword\WBSeller\API\Endpoint\Adv
 */
enum AdvertType: int
{
    /** @deprecated Устаревший тип: реклама в каталоге. */
    case ON_CATALOG    = 4;

    /** @deprecated Устаревший тип: реклама в карточке товара. */
    case ON_CARD       = 5;

    /** @deprecated Устаревший тип: реклама в поиске. */
    case ON_SEARCH     = 6;

    /** @deprecated Устаревший тип: реклама в рекомендациях на главной странице. */
    case ON_HOME_RECOM = 7;

    /** Автоматическая кампания. */
    case AUTO          = 8;

    /** Реклама в поиске и каталоге. */
    case ON_SEARCH_CATALOG = 9;

    /**
     * @deprecated Используйте self::cases() или array_column(self::cases(), 'value').
     * @return int[]
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
