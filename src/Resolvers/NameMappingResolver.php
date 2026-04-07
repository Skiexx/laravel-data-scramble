<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Resolvers;

use Spatie\LaravelData\Support\DataProperty;

/**
 * Определяет итоговое имя свойства с учётом маппинга.
 *
 * Поддерживает #[MapInputName], #[MapOutputName], #[MapName],
 * а также маперы SnakeCaseMapper, CamelCaseMapper и пользовательские.
 *
 * laravel-data предварительно вычисляет маппинг при построении DataProperty,
 * поэтому здесь используются готовые значения inputMappedName/outputMappedName.
 */
class NameMappingResolver
{
    /**
     * Возвращает имя свойства для OpenAPI-схемы.
     *
     * Для direction='input' используется inputMappedName (параметры запроса),
     * для direction='output' — outputMappedName (свойства ответа).
     * Если маппинг не задан — возвращает оригинальное имя PHP-свойства.
     */
    public function resolve(DataProperty $property, string $direction = 'output'): string
    {
        $mappedName = $direction === 'input'
            ? $property->inputMappedName
            : $property->outputMappedName;

        return $mappedName ?? $property->name;
    }
}
