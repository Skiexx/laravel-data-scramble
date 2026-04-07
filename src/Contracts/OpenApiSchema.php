<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Contracts;

/**
 * Интерфейс для ручного определения OpenAPI-схемы любого класса.
 *
 * Классы, реализующие этот интерфейс, автоматически распознаются
 * расширением LaravelDataTypeToSchemaExtension. Схема из openApiSchema()
 * используется вместо автоматической генерации из свойств класса.
 *
 * Может применяться как к Data-классам, так и к любым другим классам,
 * используемым в качестве типов возврата контроллера.
 */
interface OpenApiSchema
{
    /**
     * Возвращает массив-описание OpenAPI-схемы.
     *
     * Формат соответствует спецификации OpenAPI Schema Object:
     * - type: тип (object, string, integer и др.)
     * - properties: описание свойств для object
     * - required: список обязательных свойств
     * - format, enum, pattern, nullable, description: дополнительные ограничения
     *
     * @return array<string, mixed>
     */
    public static function openApiSchema(): array;
}
