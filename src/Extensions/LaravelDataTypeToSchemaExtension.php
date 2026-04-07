<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Extensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Skiexx\LaravelDataScramble\Contracts\OpenApiSchema;
use Skiexx\LaravelDataScramble\Resolvers\DataClassSchemaResolver;
use Spatie\LaravelData\Contracts\BaseData;

/**
 * Расширение Scramble для автоматической генерации OpenAPI-схем из Data-классов.
 *
 * Перехватывает типы, являющиеся наследниками BaseData (Data, Resource, Dto)
 * или реализующие интерфейс OpenApiSchema, и преобразует их в OpenAPI ObjectType
 * с регистрацией в #/components/schemas/ через $ref-ссылки.
 */
class LaravelDataTypeToSchemaExtension extends TypeToSchemaExtension
{
    /** Определяет, должен ли этот extension обрабатывать переданный тип. */
    public function shouldHandle(Type $type): bool
    {
        if (!$type instanceof ObjectType) {
            return false;
        }

        if ($type->isInstanceOf(BaseData::class)) {
            return true;
        }

        if (class_exists($type->name) && in_array(OpenApiSchema::class, class_implements($type->name) ?: [])) {
            return true;
        }

        return false;
    }

    /** Преобразует PHP-тип Data-класса в OpenAPI-схему через DataClassSchemaResolver. */
    public function toSchema(Type $type): OpenApiType
    {
        /** @var ObjectType $type */
        $resolver = new DataClassSchemaResolver($this->components);

        return $resolver->resolve($type->name);
    }

    /** Создаёт $ref-ссылку для вынесения схемы в #/components/schemas/. */
    public function reference(ObjectType $type): Reference
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }
}
