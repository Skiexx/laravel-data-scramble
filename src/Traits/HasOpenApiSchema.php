<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Traits;

use Skiexx\LaravelDataScramble\Resolvers\DataClassSchemaResolver;

/**
 * Реализация OpenApiSchema по умолчанию для Data-классов.
 *
 * Автоматически генерирует массив-схему из свойств класса
 * через DataClassSchemaResolver, без необходимости вручную
 * описывать каждое свойство в openApiSchema().
 */
trait HasOpenApiSchema
{
    /**
     * Генерирует OpenAPI-схему из свойств текущего класса.
     *
     * @return array<string, mixed>
     */
    public static function openApiSchema(): array
    {
        return DataClassSchemaResolver::toArray(static::class);
    }
}
