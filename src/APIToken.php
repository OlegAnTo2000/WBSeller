<?php

declare(strict_types=1);

namespace Dakword\WBSeller;

use Dakword\WBSeller\Enum\ApiName;
use Dakword\WBSeller\Exception\WBSellerException;
use DateTime;

/**
 * Парсер и валидатор токена WildBerries API.
 *
 * Токен WB — это JWT-подобная строка формата `header.payload.signature`,
 * где payload закодирован в Base64. Класс декодирует payload и предоставляет
 * удобные методы для проверки срока действия, типа токена и прав доступа.
 *
 * Структура payload:
 *   - `exp`  — Unix-timestamp истечения токена
 *   - `s`    — битовая маска разрешений (каждый бит = одна группа API)
 *   - `sid`  — UUID продавца
 *   - `acc`  — тип токена (1=basic, 2=test, 3=personal, 4=service)
 *   - `t`    — флаг (boolean)
 *   - `oid`  — числовой ID продавца (опционально)
 *   - `for`  — идентификатор сервиса для service-токенов (опционально)
 *
 * Подводный камень: конструктор бросает WBSellerException при неверном формате,
 * поэтому оборачивайте создание в try/catch при ненадёжном источнике токена.
 *
 * @throws \Dakword\WBSeller\Exception\WBSellerException при неверном формате токена
 */
class APIToken
{
    const TYPE_BASIC    = 1;
    const TYPE_TEST     = 2;
    const TYPE_PERSONAL = 3;
    const TYPE_SERVICE  = 4;

    /**
     * Карта битовых позиций к человекочитаемым названиям групп API.
     *
     * Ключ — позиция бита в поле `s` payload-а.
     * Значение — название группы для отображения пользователю.
     * Позиция 30 (BIT_READONLY) — специальный флаг «только чтение».
     */
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

    /**
     * Маппинг названий endpoint на позицию бита в маске разрешений `s`.
     *
     * Позиция 0 означает «доступ не требует специального разрешения» (common, tariffs).
     * Несколько endpoint могут разделять один бит (calendar/prices — бит 3,
     * feedbacks/questions — бит 7): это особенность группировки прав в токене WB.
     */
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

    /**
     * Парсит токен WB и декодирует его payload.
     *
     * @param string $token Токен в формате `xxxxx.yyyyy.zzzzz` (три части через точку)
     * @throws \Dakword\WBSeller\Exception\WBSellerException если формат неверный или
     *         payload не является корректным Base64-JSON с обязательными полями
     */
    function __construct(string $token)
    {
        $this->token = $token;

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new WBSellerException('Неверный формат токена');
        }
        $encodedPayload = strtr($parts[1], '-_', '+/');
        $encodedPayload .= str_repeat('=', (4 - strlen($encodedPayload) % 4) % 4);
        $decodedPayload = base64_decode($encodedPayload, true);
        if ($decodedPayload === false) {
            throw new WBSellerException('Неверный формат токена');
        }

        $this->payload = json_decode($decodedPayload);

        if (json_last_error() !== JSON_ERROR_NONE || !is_object($this->payload)) {
            throw new WBSellerException('Неверный формат токена');
        }

        foreach (['exp', 's', 'sid', 'acc', 't'] as $param) {
            if (!property_exists($this->payload, $param)) {
                throw new WBSellerException('Неверный формат токена');
            }
        }
    }

    /** Возвращает исходную строку токена. */
    public function __toString(): string
    {
        return $this->token;
    }

    /** Возвращает декодированный payload как stdClass для прямого доступа к полям. */
    public function getPayload(): object
    {
        return $this->payload;
    }

    /** Дата и время истечения токена (из поля `exp` payload). */
    public function expireDate(): DateTime
    {
        return (new DateTime())->setTimestamp($this->payload->exp);
    }

    /** Истёк ли токен по текущему системному времени. */
    public function isExpired(): bool
    {
        return (new DateTime()) > $this->expireDate();
    }

    /** Количество полных дней до истечения токена (отрицательное — уже истёк). */
    public function daysUntilExpiry(): int
    {
        return (int) floor(($this->payload->exp - time()) / 86400);
    }

    /** Количество полных часов до истечения токена (отрицательное — уже истёк). */
    public function hoursUntilExpiry(): int
    {
        return (int) floor(($this->payload->exp - time()) / 3600);
    }

    /**
     * Возвращает замаскированную версию токена для логов.
     *
     * Показывает только первые 5 и последние 5 символов, остальное скрыто.
     * Пример: `eyJhb...kVjaQ`
     */
    public function masked(): string
    {
        return substr($this->token, 0, 5) . '...' . substr($this->token, -5);
    }

    /**
     * Числовой тип токена из поля `acc` payload.
     *
     * @return int Одна из констант TYPE_* (1=basic, 2=test, 3=personal, 4=service)
     */
    public function tokenType(): int
    {
        return $this->payload->acc;
    }

    /** Стандартный (базовый) токен, выдаётся в личном кабинете WB. */
    public function isBasic(): bool
    {
        return $this->payload->acc === self::TYPE_BASIC;
    }

    /** Тестовый токен с ограниченными правами (не для продакшена). */
    public function isTest(): bool
    {
        return $this->payload->acc === self::TYPE_TEST;
    }

    /** Персональный токен, привязан к конкретному пользователю. */
    public function isPersonal(): bool
    {
        return $this->payload->acc === self::TYPE_PERSONAL;
    }

    /** Сервисный токен, выданный сторонним сервисом (партнёрский). */
    public function isService(): bool
    {
        return $this->payload->acc === self::TYPE_SERVICE;
    }

    /**
     * Идентификатор сервиса для сервисных токенов.
     *
     * Поле `for` в payload имеет формат `asid:XXXX`.
     * Возвращает только часть после `asid:`, или null если токен не сервисный
     * либо поле `for` отсутствует/имеет нестандартный формат.
     */
    public function serviceId(): ?string
    {
        if (!$this->isService()) {
            return null;
        }
        $for = $this->payload->for ?? '';
        return str_starts_with($for, 'asid:') ? substr($for, 5) : null;
    }

    /**
     * Проверяет, является ли токен «только для чтения».
     *
     * Определяется битом 30 в маске `s`. Токен только для чтения не позволяет
     * выполнять мутирующие операции (создание, изменение, удаление).
     */
    public function isReadOnly(): bool
    {
        return $this->isFlagSet(self::BIT_READONLY);
    }

    /**
     * Числовой ID продавца (поле `oid` payload).
     *
     * Может отсутствовать в некоторых типах токенов — возвращает null.
     */
    public function sellerId(): ?int
    {
        return $this->payload->oid ?? null;
    }

    /** UUID продавца из поля `sid` payload — присутствует во всех токенах. */
    public function sellerUUID(): string
    {
        return $this->payload->sid;
    }

    /**
     * Возвращает список групп API, к которым токен имеет доступ.
     *
     * Ключи результата — позиции битов из константы BIT,
     * значения — человекочитаемые названия групп.
     */
    public function accessList(): array
    {
        return array_filter(self::BIT, fn($position) => $this->isFlagSet($position), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Проверяет доступ токена к конкретному endpoint по его имени.
     *
     * Имена endpoint соответствуют ключам `$apiFlagPosition`:
     * 'content', 'analytics', 'adv', 'feedbacks', 'questions' и т.д.
     * Endpoint с позицией 0 (common, tariffs) всегда возвращают true.
     * Неизвестное имя endpoint → возвращает false.
     */
    public function accessTo(string|ApiName $apiName): bool
    {
        $apiName = $apiName instanceof ApiName ? $apiName->value : $apiName;
        $position = $this->apiFlagPosition[$apiName] ?? null;
        if (is_null($position)) {
            return false;
        }
        if ($position) {
            return $this->isFlagSet($position);
        }
        return true;
    }

    /**
     * Проверяет доступ токена сразу к нескольким endpoint.
     *
     * Возвращает true только если токен имеет доступ ко ВСЕМ перечисленным endpoint.
     * Пример: `$token->hasAccess('content', 'prices', 'analytics')`
     */
    public function hasAccess(string|ApiName ...$apiNames): bool
    {
        foreach ($apiNames as $apiName) {
            if (!$this->accessTo($apiName)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Проверяет установлен ли бит на указанной позиции в маске разрешений `s`.
     *
     * Использует побитовое AND: `s & (1 << position)`.
     */
    private function isFlagSet($position): bool
    {
        return (bool) ($this->payload->s & (0b1 << $position));
    }
}
