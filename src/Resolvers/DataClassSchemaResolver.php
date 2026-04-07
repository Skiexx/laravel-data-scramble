<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Resolvers;

use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Skiexx\LaravelDataScramble\Contracts\OpenApiSchema;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Оркестратор генерации OpenAPI-схемы из Data-класса.
 *
 * Координирует работу PropertyTypeResolver, ValidationConstraintResolver
 * и NameMappingResolver для построения полной ObjectType схемы
 * со всеми свойствами, типами, ограничениями и required-массивом.
 *
 * Поддерживает два режима:
 * - Автоматический парсинг свойств Data-класса через DataConfig
 * - Ручное определение через интерфейс OpenApiSchema
 */
class DataClassSchemaResolver
{
    private PropertyTypeResolver $propertyTypeResolver;

    private ValidationConstraintResolver $validationConstraintResolver;

    private NameMappingResolver $nameMappingResolver;

    public function __construct(
        private readonly Components $components,
    ) {
        $this->propertyTypeResolver = new PropertyTypeResolver($this->components);
        $this->validationConstraintResolver = new ValidationConstraintResolver();
        $this->nameMappingResolver = new NameMappingResolver();
    }

    /**
     * Генерирует OpenAPI-схему для указанного класса.
     *
     * Если класс реализует OpenApiSchema — использует его метод openApiSchema().
     * Иначе — автоматически парсит свойства Data-класса.
     *
     * @param  class-string  $className
     */
    public function resolve(string $className, string $direction = 'output'): OpenApiType
    {
        if (is_subclass_of($className, OpenApiSchema::class) || in_array(OpenApiSchema::class, class_implements($className) ?: [])) {
            return $this->resolveFromInterface($className);
        }

        return $this->resolveFromDataClass($className, $direction);
    }

    /**
     * Статический хелпер для трейта HasOpenApiSchema.
     *
     * Возвращает схему в виде массива (не OpenApiType),
     * чтобы любой класс мог использовать через HasOpenApiSchema::openApiSchema().
     *
     * @param  class-string  $className
     * @return array<string, mixed>
     */
    public static function toArray(string $className): array
    {
        $config = app(DataConfig::class);
        $dataClass = $config->getDataClass($className);

        $properties = [];
        $required = [];

        foreach ($dataClass->properties as $property) {
            if (self::shouldSkipProperty($property)) {
                continue;
            }

            $name = $property->outputMappedName ?? $property->name;
            $type = self::resolveSimpleType($property);

            $properties[$name] = $type;

            if (!$property->type->isOptional && !$property->type->isNullable && $property->type->lazyType === null) {
                $required[] = $name;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];

        if (count($required) > 0) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Строит схему из метода openApiSchema() интерфейса OpenApiSchema.
     *
     * Преобразует массив-описание в OpenAPI ObjectType
     * с поддержкой properties, required, format, enum, nullable, pattern.
     *
     * @param  class-string  $className
     */
    private function resolveFromInterface(string $className): OpenApiType
    {
        /** @var array<string, mixed> $schema */
        $schema = $className::openApiSchema();

        $objectType = new OpenApiObjectType();

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $propertySchema) {
                $objectType->addProperty($name, $this->arrayToOpenApiType($propertySchema));
            }
        }

        if (isset($schema['required']) && is_array($schema['required'])) {
            $objectType->setRequired($schema['required']);
        }

        return $objectType;
    }

    /**
     * Строит схему из метаданных Data-класса через DataConfig.
     *
     * Для каждого свойства: определяет имя, тип, ограничения,
     * nullable, readOnly (computed), обязательность.
     *
     * @param  class-string  $className
     */
    private function resolveFromDataClass(string $className, string $direction): OpenApiObjectType
    {
        $config = app(DataConfig::class);
        $dataClass = $config->getDataClass($className);

        $objectType = new OpenApiObjectType();
        $required = [];

        foreach ($dataClass->properties as $property) {
            if (self::shouldSkipProperty($property)) {
                continue;
            }

            $propertyName = $this->nameMappingResolver->resolve($property, $direction);
            $propertyType = $this->propertyTypeResolver->resolve($property);

            $this->validationConstraintResolver->apply($property, $propertyType);

            if ($property->type->isNullable) {
                $propertyType->nullable(true);
            }

            if ($property->computed && config('laravel-data-scramble.computed_as_readonly', true)) {
                $propertyType->setAttribute('readOnly', true);
            }

            $objectType->addProperty($propertyName, $propertyType);

            if ($this->isRequired($property)) {
                $required[] = $propertyName;
            }
        }

        if (count($required) > 0) {
            $objectType->setRequired($required);
        }

        return $objectType;
    }

    /** Определяет, является ли свойство обязательным в схеме. */
    private function isRequired(DataProperty $property): bool
    {
        if ($property->type->isNullable) {
            return false;
        }

        if ($property->type->isOptional) {
            return false;
        }

        if ($property->type->lazyType !== null && config('laravel-data-scramble.lazy_as_optional', true)) {
            return false;
        }

        if ($property->hasDefaultValue) {
            return false;
        }

        return true;
    }

    /** Определяет, нужно ли пропустить свойство (hidden). */
    private static function shouldSkipProperty(DataProperty $property): bool
    {
        if ($property->hidden && config('laravel-data-scramble.skip_hidden', true)) {
            return true;
        }

        return false;
    }

    /**
     * Конвертирует массив-описание свойства в OpenApiType.
     *
     * Используется при обработке OpenApiSchema::openApiSchema().
     *
     * @param  array<string, mixed>  $schema
     */
    private function arrayToOpenApiType(array $schema): OpenApiType
    {
        $type = $schema['type'] ?? 'string';

        $openApiType = match ($type) {
            'string' => new \Dedoc\Scramble\Support\Generator\Types\StringType(),
            'integer' => new \Dedoc\Scramble\Support\Generator\Types\IntegerType(),
            'number' => new \Dedoc\Scramble\Support\Generator\Types\NumberType(),
            'boolean' => new \Dedoc\Scramble\Support\Generator\Types\BooleanType(),
            'array' => new \Dedoc\Scramble\Support\Generator\Types\ArrayType(),
            'object' => new OpenApiObjectType(),
            default => new \Dedoc\Scramble\Support\Generator\Types\StringType(),
        };

        if (isset($schema['format'])) {
            $openApiType->format($schema['format']);
        }

        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $openApiType->enum($schema['enum']);
        }

        if (isset($schema['nullable']) && $schema['nullable'] === true) {
            $openApiType->nullable(true);
        }

        if (isset($schema['description'])) {
            $openApiType->setDescription($schema['description']);
        }

        if (isset($schema['pattern'])) {
            $openApiType->pattern($schema['pattern']);
        }

        return $openApiType;
    }

    /**
     * Упрощённое определение типа для статического метода toArray().
     *
     * Не использует Components (нет $ref), только базовые типы.
     *
     * @return array<string, mixed>
     */
    private static function resolveSimpleType(DataProperty $property): array
    {
        $acceptedTypes = $property->type->getAcceptedTypes();
        $typeNames = array_keys($acceptedTypes);
        $typeName = $typeNames[0] ?? 'string';

        return match ($typeName) {
            'string' => ['type' => 'string'],
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            default => ['type' => 'string'],
        };
    }
}
