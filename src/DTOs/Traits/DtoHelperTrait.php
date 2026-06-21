<?php

declare(strict_types=1);

namespace Dakword\WBSeller\DTOs\Traits;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Трейт для гидрации и сериализации DTO из/в данные WB API.
 *
 * Предоставляет универсальные методы преобразования:
 *   - массив/JSON → DTO (`fromArray`, `fromResponse`)
 *   - объект stdClass → DTO (`fromObject`)
 *   - DTO → массив (`toArray`)
 *
 * Типизация основана на type-hint публичных свойств DTO через Reflection.
 * Поддерживаются: скалярные типы, DateTime, BackedEnum, вложенные DTO (рекурсивно).
 *
 * Режимы гидрации:
 *   - tolerant (по умолчанию): неизвестные поля API складываются в `$extra`,
 *     отсутствующие поля DTO остаются незаполненными (null/default).
 *   - strict: неизвестные поля бросают InvalidArgumentException.
 *
 * Чтобы использовать трейт, объявите его в DTO-классе:
 * ```php
 * class MyDTO {
 *     use DtoHelperTrait;
 *     public int $id;
 *     public string $name;
 *     public array $extra = []; // опционально: для неизвестных полей
 * }
 * $dto = MyDTO::fromArray($apiResponseArray);
 * ```
 *
 * Подводный камень: union-типы (int|string) не поддерживаются в `castValue` —
 * значение передаётся без кастинга. Используйте только именованные типы.
 */
trait DtoHelperTrait
{

	/**
	 * Все неизвестные поля из ответа складываем сюда (если свойство объявлено).
	 * Рекомендуется объявлять в DTO:
	 *   public array $extra = [];
	 */
	protected static string $extraProperty = 'extra';

	/**
	 * Создаёт экземпляр DTO из ассоциативного массива (декодированный JSON).
	 *
	 * Перебирает публичные свойства DTO через Reflection и заполняет их значениями
	 * из `$data`, применяя кастинг на основе type-hint каждого свойства.
	 *
	 * В tolerant-режиме (по умолчанию) неизвестные ключи из `$data` попадают
	 * в свойство `$extra` (если оно объявлено в DTO). Это полезно, когда WB
	 * добавляет новые поля в ответ — они не ломают гидрацию.
	 *
	 * @param array $data   Ассоциативный массив (обычно результат json_decode(..., true))
	 * @param bool  $strict Если true, неизвестные поля бросают InvalidArgumentException
	 * @throws \InvalidArgumentException В strict-режиме при наличии неизвестных полей
	 */
	public static function fromArray(array $data, bool $strict = false): static
	{
		$dto = new static();
		$ref = new ReflectionClass($dto);

		/** @var array<string, ReflectionProperty> $props */
		$props = [];
		foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $p) {
			$props[$p->getName()] = $p;
		}

		$extra = [];

		foreach ($data as $key => $value) {
			if (!isset($props[$key])) {
				if ($strict) {
					throw new \InvalidArgumentException(sprintf('Unknown field "%s" for %s', (string)$key, $ref->getName()));
				}
				$extra[$key] = $value;
				continue;
			}

			$dto->$key = static::castValue($props[$key], $value);
		}

		if (!$strict && isset($props[static::$extraProperty])) {
			$dto->{static::$extraProperty} = $extra;
		}

		return $dto;
	}

	/**
	 * Создаёт DTO из ответа API (JSON-строка или уже декодированный массив).
	 *
	 * Удобная обёртка над `fromArray`: если передана строка — декодирует JSON,
	 * если массив — делегирует напрямую. Используйте, когда тип ответа неизвестен.
	 *
	 * @param array|string $response JSON-строка или ассоциативный массив
	 * @throws \InvalidArgumentException Если строка не является корректным JSON-массивом
	 */
	public static function fromResponse(array|string $response, bool $strict = false): static
	{
		if (is_string($response)) {
			$decoded = json_decode($response, true);
			if (!is_array($decoded)) {
				throw new \InvalidArgumentException('Response JSON must decode to array');
			}
			return static::fromArray($decoded, $strict);
		}

		return static::fromArray($response, $strict);
	}

	/**
	 * Сериализует DTO в ассоциативный массив.
	 *
	 * Рекурсивно нормализует значения: DateTime → ISO 8601 строка,
	 * BackedEnum → scalar value, вложенные DTO → массив через toArray().
	 *
	 * @param bool $includeExtra Включать ли поле `$extra` в результат (по умолчанию true)
	 */
	public function toArray(bool $includeExtra = true): array
	{
		$result = [];
		$ref = new ReflectionClass($this);

		foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			$name = $property->getName();
			$value = $this->$name;

			if ($name === static::$extraProperty && !$includeExtra) {
				continue;
			}

			$result[$name] = static::normalizeOut($value);
		}

		return $result;
	}

	/**
	 * Возвращает значение из поля `$extra` по ключу.
	 *
	 * Поле `$extra` содержит неизвестные поля из ответа API (в tolerant-режиме).
	 * Если свойство `$extra` не объявлено или ключ отсутствует — возвращает `$default`.
	 */
	public function extra(string $key, mixed $default = null): mixed
	{
		$prop = static::$extraProperty;

		if (!property_exists($this, $prop) || !is_array($this->$prop)) {
			return $default;
		}

		return array_key_exists($key, $this->$prop) ? $this->$prop[$key] : $default;
	}

	/**
	 * Приводит значение к типу, объявленному в type-hint свойства DTO.
	 *
	 * Обрабатывает: int, float, bool, string, array, DateTimeImmutable, BackedEnum,
	 * вложенные DTO (через castObjectType). Union/intersection типы — без кастинга.
	 * null всегда возвращается как null (независимо от типа).
	 */
	protected static function castValue(ReflectionProperty $property, mixed $value): mixed
	{
		if ($value === null) {
			return null;
		}

		$type = $property->getType();
		if (!$type instanceof ReflectionNamedType) {
			// union/complex types — не трогаем в базовой версии
			return $value;
		}

		$typeName = $type->getName();

		// nullable уже обработан (null выше)
		return match ($typeName) {
			'int'    => is_int($value) ? $value : (int)$value,
			'float'  => is_float($value) ? $value : (float)$value,
			'bool'   => is_bool($value) ? $value : static::toBool($value),
			'string' => is_string($value) ? $value : (string)$value,
			'array'  => is_array($value) ? $value : [$value],

			DateTimeImmutable::class => static::toDateTimeImmutable($value),

			default => static::castObjectType($typeName, $value),
		};
	}

	/**
	 * Приводит значение к сложному типу: BackedEnum или вложенный DTO.
	 *
	 * Порядок проверок:
	 *   1. BackedEnum — вызывает `::from()`, бросает UnexpectedValueException при неверном значении.
	 *   2. Вложенный DTO — если тип имеет метод `fromArray` и значение является массивом.
	 *   3. Всё остальное — возвращается без изменений (может быть stdClass, object и т.д.).
	 */
	protected static function castObjectType(string $typeName, mixed $value): mixed
	{
		// BackedEnum
		if (is_subclass_of($typeName, BackedEnum::class)) {
			// Enum::from кидает ValueError на неизвестных значениях — это ок,
			// но иногда сервер может прислать мусор -> лучше явно поймать и дать понятную ошибку.
			try {
				/** @var class-string<BackedEnum> $typeName */
				return $typeName::from($value);
			} catch (\ValueError $e) {
				throw new \UnexpectedValueException(sprintf('Invalid enum value "%s" for %s', (string)$value, $typeName), 0, $e);
			}
		}

		// Вложенный DTO: если тип имеет fromArray и значение массив
		if (is_array($value) && method_exists($typeName, 'fromArray')) {
			/** @phpstan-ignore-next-line */
			return $typeName::fromArray($value);
		}

		// Коллекции/объекты/что угодно — оставляем как есть
		return $value;
	}

	/**
	 * Нестрогое приведение к bool.
	 *
	 * Распознаёт строковые значения: '1'/'true'/'yes'/'y'/'on' → true,
	 * '0'/'false'/'no'/'n'/'off'/'' → false. Прочие строки — через (bool).
	 * Числа: 0/0.0 → false, остальные → true.
	 */
	protected static function toBool(mixed $value): bool
	{
		if (is_bool($value)) return $value;
		if (is_int($value) || is_float($value)) return (bool)$value;

		$v = strtolower(trim((string)$value));
		if (in_array($v, ['1', 'true', 'yes', 'y', 'on'], true)) return true;
		if (in_array($v, ['0', 'false', 'no', 'n', 'off', ''], true)) return false;

		// fallback: PHP поведение может удивлять, но лучше явно:
		return (bool)$value;
	}

	/**
	 * Приводит значение к DateTimeImmutable.
	 *
	 * WB обычно присылает ISO 8601 (например '2024-01-15T10:30:00+03:00').
	 * Если строка не парсится — бросает UnexpectedValueException с оригинальным значением.
	 * Принимает также DateTimeInterface (конвертирует) и уже готовый DateTimeImmutable.
	 */
	protected static function toDateTimeImmutable(mixed $value): ?DateTimeImmutable
	{
		if ($value instanceof DateTimeImmutable) {
			return $value;
		}
		if ($value instanceof DateTimeInterface) {
			return new DateTimeImmutable($value->format(DateTimeInterface::ATOM));
		}

		// WB/Ozon часто присылают ISO 8601, иногда без таймзоны.
		// DateTimeImmutable это съест, но если формат нестабилен — упадёт.
		try {
			return new DateTimeImmutable((string)$value);
		} catch (\Exception $e) {
			throw new \UnexpectedValueException(sprintf('Invalid datetime "%s"', (string)$value), 0, $e);
		}
	}

	protected static function normalizeOut(mixed $value): mixed
	{
		if ($value instanceof BackedEnum) {
			return $value->value;
		}

		if ($value instanceof DateTimeInterface) {
			return $value->format(DateTimeInterface::ATOM);
		}

		if (is_array($value)) {
			$out = [];
			foreach ($value as $k => $v) {
				$out[$k] = static::normalizeOut($v);
			}
			return $out;
		}

		// вложенные DTO
		if (is_object($value) && method_exists($value, 'toArray')) {
			/** @phpstan-ignore-next-line */
			return $value->toArray();
		}

		return $value;
	}

	/**
	 * Создание DTO из объекта (stdClass или любого объекта с public-свойствами).
	 */
	public static function fromObject(object $object, bool $strict = false): static
	{
		return static::fromArray(
			static::objectToArray($object),
			$strict
		);
	}

	/**
	 * Нормализация объекта в массив.
	 *
	 * Поддерживает:
	 * - stdClass
	 * - любой объект с public-свойствами
	 * - вложенные stdClass / массивы
	 *
	 * НЕ:
	 * - вызывает геттеры
	 * - не лезет в private/protected
	 */
	protected static function objectToArray(object $object): array
	{
		// stdClass и подобные
		if ($object instanceof \stdClass) {
			return static::normalizeObjectVars(get_object_vars($object));
		}

		// Любой другой объект: только public properties
		$ref = new \ReflectionObject($object);
		$data = [];

		foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
			if (!$property->isInitialized($object)) {
				continue;
			}

			$data[$property->getName()] = $property->getValue($object);
		}

		return static::normalizeObjectVars($data);
	}

	/**
	 * Рекурсивная нормализация значений:
	 * - object -> array
	 * - array -> array
	 */
	protected static function normalizeObjectVars(array $data): array
	{
		foreach ($data as $key => $value) {
			if (is_object($value)) {
				$data[$key] = static::objectToArray($value);
			} elseif (is_array($value)) {
				foreach ($value as $k => $v) {
					if (is_object($v)) {
						$value[$k] = static::objectToArray($v);
					}
				}
				$data[$key] = $value;
			}
		}

		return $data;
	}
}
