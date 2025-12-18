<?php

declare(strict_types=1);

namespace Dakword\WBSeller\DTOs\Traits;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

trait DtoHelperTrait
{

	/**
	 * Все неизвестные поля из ответа складываем сюда (если свойство объявлено).
	 * Рекомендуется объявлять в DTO:
	 *   public array $extra = [];
	 */
	protected static string $extraProperty = 'extra';

	/**
	 * Создание DTO из decoded JSON / массива.
	 * По умолчанию tolerant: неизвестные поля -> extra, missing fields -> null/дефолт.
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
	 * Создание DTO из ответа (массив или JSON-строка).
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
	 * Сериализация DTO в массив для логов/отладки.
	 * DateTime -> string (ATOM), Enum -> value.
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
	 * Удобный доступ к extra.
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
	 * Базовый кастинг на основании type-hint public-свойства.
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
