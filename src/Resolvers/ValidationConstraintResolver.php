<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Resolvers;

use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Skiexx\LaravelDataScramble\Support\ValidationAttributeMap;
use Spatie\LaravelData\Attributes\Validation\ValidationAttribute;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Применяет атрибуты валидации laravel-data к OpenAPI-типу.
 *
 * Проходит по всем ValidationAttribute свойства Data-класса
 * и делегирует каждый в ValidationAttributeMap для маппинга
 * в OpenAPI-ограничения (minLength, format, pattern и др.).
 */
class ValidationConstraintResolver
{
    /** Применяет все валидационные атрибуты свойства к OpenAPI-типу. */
    public function apply(DataProperty $property, OpenApiType $type): void
    {
        foreach ($property->attributes->all(ValidationAttribute::class) as $attribute) {
            ValidationAttributeMap::apply($attribute, $type);
        }
    }
}
