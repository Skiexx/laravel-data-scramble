<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Resolvers;

use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType as OpenApiBooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType as OpenApiIntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType as OpenApiNumberType;
use Dedoc\Scramble\Support\Generator\Types\StringType as OpenApiStringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType as OpenApiUnknownType;
use ReflectionEnum;
use Skiexx\LaravelDataScramble\Contracts\OpenApiSchema;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Types\CombinationType;
use Spatie\LaravelData\Support\Types\NamedType;

/**
 * Преобразует PHP-тип свойства Data-класса в OpenAPI-тип.
 *
 * Поддерживает: скалярные типы, даты (Carbon, DateTime), backed enum,
 * вложенные Data-объекты ($ref), коллекции Data, union-типы с null.
 */
class PropertyTypeResolver
{
    /** @var class-string[] */
    private const DATETIME_CLASSES = [
        Carbon::class,
        CarbonImmutable::class,
        DateTime::class,
        DateTimeImmutable::class,
        DateTimeInterface::class,
    ];

    public function __construct(
        private readonly Components $components,
    ) {
    }

    /** Определяет OpenAPI-тип для свойства Data-класса. */
    public function resolve(DataProperty $property): OpenApiType
    {
        $propertyType = $property->type;

        if ($propertyType->isMixed) {
            return new OpenApiUnknownType();
        }

        if ($propertyType->kind->isDataObject() && $propertyType->dataClass !== null) {
            return $this->resolveDataReference($propertyType->dataClass);
        }

        if ($propertyType->kind->isDataCollectable()) {
            $itemClass = $propertyType->dataCollectableClass;

            if ($itemClass !== null && class_exists($itemClass)) {
                $arrayType = new OpenApiArrayType();
                $arrayType->setItems($this->resolveDataReference($itemClass));

                return $arrayType;
            }

            return new OpenApiArrayType();
        }

        $type = $propertyType->type;

        if ($type instanceof NamedType) {
            return $this->resolveNamedType($type);
        }

        if ($type instanceof CombinationType) {
            return $this->resolveCombinationType($type);
        }

        return new OpenApiUnknownType();
    }

    /**
     * Преобразует NamedType (конкретный PHP-тип) в OpenAPI-тип.
     *
     * Маршрутизация: built-in → скаляры, DateTime → date-time,
     * BackedEnum → enum, Data-наследник → $ref.
     */
    private function resolveNamedType(NamedType $type): OpenApiType
    {
        return match (true) {
            $type->builtIn => $this->resolveBuiltInType($type->name),
            $this->isDateTimeType($type->name) => (new OpenApiStringType())->format('date-time'),
            $type->name === DateInterval::class => (new OpenApiStringType())->format('duration'),
            $this->isBackedEnum($type->name) => $this->resolveEnumType($type->name),
            is_subclass_of($type->name, BaseData::class) => $this->resolveDataReference($type->name),
            $this->implementsOpenApiSchema($type->name) => $this->resolveOpenApiSchema($type->name),
            default => new OpenApiStringType(),
        };
    }

    /** Маппинг встроенных PHP-типов в OpenAPI: string, int, float, bool, array. */
    private function resolveBuiltInType(string $typeName): OpenApiType
    {
        return match ($typeName) {
            'string' => new OpenApiStringType(),
            'int' => new OpenApiIntegerType(),
            'float' => new OpenApiNumberType(),
            'bool' => new OpenApiBooleanType(),
            'array' => new OpenApiArrayType(),
            'null' => (new OpenApiStringType())->nullable(true),
            default => new OpenApiUnknownType(),
        };
    }

    /** Проверяет, является ли класс DateTime-совместимым типом. */
    private function isDateTimeType(string $className): bool
    {
        foreach (self::DATETIME_CLASSES as $dateTimeClass) {
            if ($className === $dateTimeClass || is_subclass_of($className, $dateTimeClass)) {
                return true;
            }
        }

        return false;
    }

    /** Проверяет, является ли класс backed enum. */
    private function isBackedEnum(string $className): bool
    {
        return enum_exists($className) && is_subclass_of($className, BackedEnum::class);
    }

    /** Проверяет, реализует ли класс интерфейс OpenApiSchema. */
    private function implementsOpenApiSchema(string $className): bool
    {
        return class_exists($className)
            && in_array(OpenApiSchema::class, class_implements($className) ?: []);
    }

    /**
     * Преобразует класс с OpenApiSchema в OpenAPI-тип.
     *
     * Вызывает openApiSchema() и конвертирует массив в OpenApiType.
     *
     * @param  class-string<OpenApiSchema>  $className
     */
    private function resolveOpenApiSchema(string $className): OpenApiType
    {
        /** @var array<string, mixed> $schema */
        $schema = $className::openApiSchema();

        $type = $schema['type'] ?? 'string';

        $openApiType = match ($type) {
            'string' => new OpenApiStringType(),
            'integer' => new OpenApiIntegerType(),
            'number' => new OpenApiNumberType(),
            'boolean' => new OpenApiBooleanType(),
            'array' => new OpenApiArrayType(),
            default => new OpenApiStringType(),
        };

        if (isset($schema['format'])) {
            $openApiType->format($schema['format']);
        }

        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $openApiType->enum($schema['enum']);
        }

        if (isset($schema['pattern'])) {
            $openApiType->pattern($schema['pattern']);
        }

        return $openApiType;
    }

    /**
     * Преобразует backed enum в OpenAPI-тип с перечислением допустимых значений.
     *
     * Для string-backed → StringType + enum, для int-backed → IntegerType + enum.
     *
     * @param  class-string<BackedEnum>  $enumClass
     */
    private function resolveEnumType(string $enumClass): OpenApiType
    {
        $reflection = new ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType();

        $cases = array_map(
            fn (BackedEnum $case) => $case->value,
            $enumClass::cases(),
        );

        $openApiType = $backingType?->getName() === 'int'
            ? new OpenApiIntegerType()
            : new OpenApiStringType();

        return $openApiType->enum($cases);
    }

    /**
     * Создаёт $ref-ссылку на схему Data-класса в components/schemas.
     *
     * Если схема ещё не зарегистрирована в components — генерирует её
     * через DataClassSchemaResolver и добавляет. Это гарантирует что
     * вложенные Data-объекты не создают битых $ref-ссылок.
     *
     * @param  class-string  $dataClass
     */
    private function resolveDataReference(string $dataClass): OpenApiType
    {
        $schemaName = class_basename($dataClass);

        if (!$this->components->hasSchema($schemaName)) {
            // Ставим заглушку чтобы избежать бесконечной рекурсии
            // при циклических зависимостях (A -> B -> A)
            $this->components->addSchema($schemaName, Schema::fromType(new OpenApiObjectType()));

            $resolver = new DataClassSchemaResolver($this->components);
            $schema = $resolver->resolve($dataClass);

            $this->components->addSchema($schemaName, Schema::fromType($schema));
        }

        return new Reference('schemas', $schemaName, $this->components);
    }

    /**
     * Обрабатывает union-типы (T|null), извлекая первый не-null тип.
     *
     * Nullable определяется на уровне DataPropertyType, поэтому
     * здесь просто находим основной тип из union.
     */
    private function resolveCombinationType(CombinationType $type): OpenApiType
    {
        $nonNullTypes = [];

        foreach ($type->types as $subType) {
            if ($subType instanceof NamedType && $subType->name === 'null') {
                continue;
            }

            $nonNullTypes[] = $subType;
        }

        if (count($nonNullTypes) === 1 && $nonNullTypes[0] instanceof NamedType) {
            return $this->resolveNamedType($nonNullTypes[0]);
        }

        if (count($nonNullTypes) > 0 && $nonNullTypes[0] instanceof NamedType) {
            return $this->resolveNamedType($nonNullTypes[0]);
        }

        return new OpenApiUnknownType();
    }
}
