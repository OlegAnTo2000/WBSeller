<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Enum;

/**
 * Варианты ответа продавца на заявку покупателя о возврате товара.
 *
 * Одобряющие действия (APPROVE_*): принять возврат с разными условиями.
 * Отклоняющие действия (REJECT_*): отказать в возврате с указанием причины.
 *
 * Используется в Returns endpoint при отправке решения продавца.
 * Значение enum (.value) соответствует строке `action` в запросе к WB API.
 *
 * Важно: после отправки решения изменить его нельзя — WB фиксирует ответ.
 *
 * @see \Dakword\WBSeller\API\Endpoint\Returns
 */
enum ReturnAction: string
{
    /** Одобрить с проверкой брака. */
    case APPROVE_CHECK    = 'approve1';

    /** Одобрить и забрать товар у покупателя. */
    case APPROVE_RETURN   = 'approve2';

    /** Одобрить без физического возврата товара. */
    case APPROVE_NORETURN = 'autorefund1';

    /** Отклонить — брак не обнаружен. */
    case REJECT_NODEFECT  = 'reject1';

    /** Отклонить — попросить добавить фото/видео. */
    case REJECT_ADDMEDIA  = 'reject2';

    /** Отклонить — направить в сервисный центр. */
    case REJECT_SERVICE   = 'reject3';

    /** Отклонить с произвольным комментарием продавца. */
    case REJECT_CUSTOM    = 'rejectcustom';

    // --- Deprecated aliases для обратной совместимости ---
    // В PHP 8.1 enum допускает class-константы наряду с case-ами.
    // Эти константы сохраняют старые имена ACTION_* и возвращают строковые значения,
    // чтобы существующий код не сломался при обновлении.

    /** @deprecated Используйте ReturnAction::APPROVE_CHECK->value */
    const ACTION_APPROVE_CHECK    = 'approve1';
    /** @deprecated Используйте ReturnAction::APPROVE_RETURN->value */
    const ACTION_APPROVE_RETURN   = 'approve2';
    /** @deprecated Используйте ReturnAction::APPROVE_NORETURN->value */
    const ACTION_APPROVE_NORETURN = 'autorefund1';
    /** @deprecated Используйте ReturnAction::REJECT_NODEFECT->value */
    const ACTION_REJECT_NODEFECT  = 'reject1';
    /** @deprecated Используйте ReturnAction::REJECT_ADDMEDIA->value */
    const ACTION_REJECT_ADDMEDIA  = 'reject2';
    /** @deprecated Используйте ReturnAction::REJECT_SERVICE->value */
    const ACTION_REJECT_SERVICE   = 'reject3';
    /** @deprecated Используйте ReturnAction::REJECT_CUSTOM->value */
    const ACTION_REJECT_CUSTOM    = 'rejectcustom';

    /**
     * @deprecated Используйте self::cases() или array_column(self::cases(), 'value').
     * @return string[]
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
