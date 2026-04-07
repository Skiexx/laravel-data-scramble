<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Support;

use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\NumberType as OpenApiNumberType;
use Dedoc\Scramble\Support\Generator\Types\StringType as OpenApiStringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Spatie\LaravelData\Attributes\Validation\Alpha;
use Spatie\LaravelData\Attributes\Validation\AlphaDash;
use Spatie\LaravelData\Attributes\Validation\AlphaNumeric;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Digits;
use Spatie\LaravelData\Attributes\Validation\DigitsBetween;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\EndsWith;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Image;
use Spatie\LaravelData\Attributes\Validation\IP;
use Spatie\LaravelData\Attributes\Validation\IPv4;
use Spatie\LaravelData\Attributes\Validation\IPv6;
use Spatie\LaravelData\Attributes\Validation\Json;
use Spatie\LaravelData\Attributes\Validation\Lowercase;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\MultipleOf;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Attributes\Validation\StartsWith;
use Spatie\LaravelData\Attributes\Validation\StringValidationAttribute;
use Spatie\LaravelData\Attributes\Validation\Ulid;
use Spatie\LaravelData\Attributes\Validation\Uppercase;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Attributes\Validation\ValidationAttribute;

/**
 * Карта маппинга атрибутов валидации laravel-data в OpenAPI-ограничения.
 *
 * Каждый атрибут валидации (Min, Max, Email, Uuid и др.) преобразуется
 * в соответствующее OpenAPI-свойство: minLength, format, pattern и т.д.
 *
 * Атрибуты без OpenAPI-аналога (Exists, Unique, Same и др.) молча пропускаются.
 */
class ValidationAttributeMap
{
    /**
     * Применяет ограничение валидационного атрибута к OpenAPI-типу.
     *
     * Мутирует переданный тип in-place, добавляя соответствующие
     * OpenAPI-свойства (min/max, format, pattern, nullable).
     */
    public static function apply(ValidationAttribute $attribute, OpenApiType $type): void
    {
        match (true) {
            $attribute instanceof Min => self::applyMinMax($type, min: self::getParam($attribute, 0)),
            $attribute instanceof Max => self::applyMinMax($type, max: self::getParam($attribute, 0)),
            $attribute instanceof Between => self::applyMinMax(
                $type,
                min: self::getParam($attribute, 0),
                max: self::getParam($attribute, 1),
            ),
            $attribute instanceof Size => self::applyMinMax(
                $type,
                min: self::getParam($attribute, 0),
                max: self::getParam($attribute, 0),
            ),
            $attribute instanceof Email => $type->format('email'),
            $attribute instanceof Url => $type->format('uri'),
            $attribute instanceof Uuid => $type->format('uuid'),
            $attribute instanceof Ulid => $type->format('ulid'),
            $attribute instanceof IP => $type->format('ip'),
            $attribute instanceof IPv4 => $type->format('ipv4'),
            $attribute instanceof IPv6 => $type->format('ipv6'),
            $attribute instanceof Date => $type->format('date'),
            $attribute instanceof Json => $type->contentMediaType('application/json'),
            $attribute instanceof Image, $attribute instanceof File => $type->format('binary'),
            $attribute instanceof Regex => $type->pattern(self::getParam($attribute, 0)),
            $attribute instanceof Digits => $type->pattern('^\d{' . self::getParam($attribute, 0) . '}$'),
            $attribute instanceof DigitsBetween => $type->pattern(
                '^\d{' . self::getParam($attribute, 0) . ',' . self::getParam($attribute, 1) . '}$'
            ),
            $attribute instanceof Alpha => $type->pattern('^[a-zA-Z]+$'),
            $attribute instanceof AlphaDash => $type->pattern('^[a-zA-Z0-9_-]+$'),
            $attribute instanceof AlphaNumeric => $type->pattern('^[a-zA-Z0-9]+$'),
            $attribute instanceof Lowercase => $type->pattern('^[a-z]+$'),
            $attribute instanceof Uppercase => $type->pattern('^[A-Z]+$'),
            $attribute instanceof MultipleOf => self::applyMultipleOf($type, self::getParam($attribute, 0)),
            $attribute instanceof Nullable => $type->nullable(true),
            $attribute instanceof StartsWith => self::applyStartsWith($type, $attribute),
            $attribute instanceof EndsWith => self::applyEndsWith($type, $attribute),
            default => null,
        };
    }

    /**
     * Применяет ограничения min/max с учётом типа OpenAPI.
     *
     * Для string → minLength/maxLength, для number → minimum/maximum,
     * для array → minItems/maxItems.
     */
    private static function applyMinMax(OpenApiType $type, mixed $min = null, mixed $max = null): void
    {
        if ($type instanceof OpenApiStringType) {
            if ($min !== null) {
                $type->setMin((int) $min);
            }
            if ($max !== null) {
                $type->setMax((int) $max);
            }
        } elseif ($type instanceof OpenApiNumberType) {
            if ($min !== null) {
                $type->setMin((int) $min);
            }
            if ($max !== null) {
                $type->setMax((int) $max);
            }
        } elseif ($type instanceof OpenApiArrayType) {
            if ($min !== null) {
                $type->setMin((int) $min);
            }
            if ($max !== null) {
                $type->setMax((int) $max);
            }
        }
    }

    /** Применяет multipleOf через x-extension (только для числовых типов). */
    private static function applyMultipleOf(OpenApiType $type, mixed $value): void
    {
        if ($type instanceof OpenApiNumberType) {
            $type->setExtensionProperty('multipleOf', (int) $value);
        }
    }

    /** Генерирует regex-паттерн для StartsWith: ^(prefix1|prefix2). */
    private static function applyStartsWith(OpenApiType $type, StartsWith $attribute): void
    {
        $params = self::getAllParams($attribute);
        if (count($params) > 0) {
            $escaped = array_map(fn (string $p): string => preg_quote($p, '/'), $params);
            $type->pattern('^(' . implode('|', $escaped) . ')');
        }
    }

    /** Генерирует regex-паттерн для EndsWith: (suffix1|suffix2)$. */
    private static function applyEndsWith(OpenApiType $type, EndsWith $attribute): void
    {
        $params = self::getAllParams($attribute);
        if (count($params) > 0) {
            $escaped = array_map(fn (string $p): string => preg_quote($p, '/'), $params);
            $type->pattern('(' . implode('|', $escaped) . ')$');
        }
    }

    /** Извлекает значение параметра из StringValidationAttribute по индексу. */
    private static function getParam(ValidationAttribute $attribute, int $index): mixed
    {
        if ($attribute instanceof StringValidationAttribute) {
            $params = $attribute->parameters();

            return $params[$index] ?? null;
        }

        return null;
    }

    /**
     * Извлекает все параметры из StringValidationAttribute.
     *
     * @return array<mixed>
     */
    private static function getAllParams(ValidationAttribute $attribute): array
    {
        if ($attribute instanceof StringValidationAttribute) {
            return $attribute->parameters();
        }

        return [];
    }
}
