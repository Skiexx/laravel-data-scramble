<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Extractors;

use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use ReflectionNamedType;
use ReflectionParameter;
use Skiexx\LaravelDataScramble\Resolvers\NameMappingResolver;
use Skiexx\LaravelDataScramble\Resolvers\PropertyTypeResolver;
use Skiexx\LaravelDataScramble\Resolvers\ValidationConstraintResolver;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Attributes\FromRouteParameterProperty;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Экстрактор параметров из Data-классов для Scramble.
 *
 * Анализирует параметры метода контроллера, определяет Data-классы
 * и преобразует их свойства в OpenAPI-параметры:
 * - GET/DELETE/HEAD → query parameters
 * - POST/PUT/PATCH → body parameters
 * - #[FromRouteParameter] → path parameters
 *
 * Регистрируется через prepend, чтобы перехватить Data-классы
 * до стандартного FormRequestParametersExtractor.
 */
class DataParametersExtractor implements ParameterExtractor
{
    public function __construct(
        private readonly Components $components,
    ) {
    }

    /**
     * Обрабатывает параметры метода контроллера.
     *
     * Находит Data-классы среди параметров, блокирует их обработку
     * стандартным FormRequestParametersExtractor и извлекает
     * OpenAPI-параметры из свойств Data-класса.
     *
     * @param  ParametersExtractionResult[]  $parameterExtractionResults
     * @return ParametersExtractionResult[]
     */
    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        $reflectionAction = $routeInfo->reflectionAction();
        if ($reflectionAction === null) {
            return $parameterExtractionResults;
        }

        foreach ($reflectionAction->getParameters() as $reflectionParam) {
            $className = $this->getDataClassName($reflectionParam);
            if ($className === null) {
                continue;
            }

            FormRequestParametersExtractor::ignoreInstanceOf($className);

            $parameters = $this->extractParameters($className, $routeInfo);
            if (count($parameters) > 0) {
                $parameterExtractionResults[] = new ParametersExtractionResult(
                    parameters: $parameters,
                    schemaName: class_basename($className),
                );
            }
        }

        return $parameterExtractionResults;
    }

    /**
     * Извлекает FQCN Data-класса из параметра метода.
     *
     * Возвращает null если параметр не является наследником BaseData.
     */
    private function getDataClassName(ReflectionParameter $reflectionParameter): ?string
    {
        $type = $reflectionParameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $className = $type->getName();
        if (!class_exists($className) || !is_subclass_of($className, BaseData::class)) {
            return null;
        }

        return $className;
    }

    /**
     * Извлекает OpenAPI-параметры из свойств Data-класса.
     *
     * Определяет location (query/body/path) для каждого свойства,
     * применяет маппинг имён и валидационные ограничения.
     *
     * @return Parameter[]
     */
    private function extractParameters(string $className, RouteInfo $routeInfo): array
    {
        $config = app(DataConfig::class);
        $dataClass = $config->getDataClass($className);

        $propertyTypeResolver = new PropertyTypeResolver($this->components);
        $validationConstraintResolver = new ValidationConstraintResolver();
        $nameMappingResolver = new NameMappingResolver();

        $isQueryMethod = in_array(
            mb_strtolower($routeInfo->method),
            RequestBodyExtension::HTTP_METHODS_WITHOUT_REQUEST_BODY,
        );

        $parameters = [];

        foreach ($dataClass->properties as $property) {
            if ($this->shouldSkipProperty($property)) {
                continue;
            }

            if ($this->isFromRouteParameterProperty($property)) {
                $parameter = $this->buildRouteParameter($property);
                if ($parameter !== null) {
                    $parameters[] = $parameter;
                }

                continue;
            }

            $in = $isQueryMethod ? 'query' : 'body';
            $propertyName = $nameMappingResolver->resolve($property, 'input');
            $openApiType = $propertyTypeResolver->resolve($property);

            $validationConstraintResolver->apply($property, $openApiType);

            if ($property->type->isNullable) {
                $openApiType->nullable(true);
            }

            $parameter = Parameter::make($propertyName, $in);
            $parameter->setSchema(Schema::fromType($openApiType));
            $parameter->required = $this->isRequired($property);

            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /** Определяет, нужно ли пропустить свойство (hidden, computed). */
    private function shouldSkipProperty(DataProperty $property): bool
    {
        if ($property->hidden && config('laravel-data-scramble.skip_hidden', true)) {
            return true;
        }

        if ($property->computed) {
            return true;
        }

        return false;
    }

    /** Проверяет, помечено ли свойство как #[FromRouteParameter] или #[FromRouteParameterProperty]. */
    private function isFromRouteParameterProperty(DataProperty $property): bool
    {
        return $property->attributes->has(FromRouteParameter::class)
            || $property->attributes->has(FromRouteParameterProperty::class);
    }

    /**
     * Создаёт path-параметр из свойства с #[FromRouteParameter].
     *
     * Имя параметра берётся из атрибута (routeParameter),
     * а не из имени PHP-свойства.
     */
    private function buildRouteParameter(DataProperty $property): ?Parameter
    {
        /** @var FromRouteParameter|null $attr */
        $attr = $property->attributes->first(FromRouteParameter::class);
        if ($attr === null) {
            $attr = $property->attributes->first(FromRouteParameterProperty::class);
        }

        if ($attr === null) {
            return null;
        }

        $propertyTypeResolver = new PropertyTypeResolver($this->components);
        $openApiType = $propertyTypeResolver->resolve($property);

        $parameter = Parameter::make($attr->routeParameter, 'path');
        $parameter->setSchema(Schema::fromType($openApiType));

        return $parameter;
    }

    /** Определяет, является ли свойство обязательным для запроса. */
    private function isRequired(DataProperty $property): bool
    {
        if ($property->type->isNullable) {
            return false;
        }

        if ($property->type->isOptional) {
            return false;
        }

        if ($property->hasDefaultValue) {
            return false;
        }

        return true;
    }
}
