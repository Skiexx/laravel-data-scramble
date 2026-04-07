<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType as OpenApiBooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType as OpenApiIntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType as OpenApiStringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\RouteInfo;
use ReflectionAttribute;
use Skiexx\LaravelDataScramble\Attributes\ResponseData;
use Skiexx\LaravelDataScramble\Resolvers\DataClassSchemaResolver;

/**
 * OperationExtension для обработки атрибута #[ResponseData] на методах контроллера.
 *
 * Читает атрибут ResponseData и подменяет response-схему операции
 * на правильную структуру с учётом обёртки { "data": ... },
 * пагинации (meta/links) и HTTP status code.
 */
class ResponseDataOperationExtension extends OperationExtension
{
    /**
     * Обрабатывает операцию: ищет #[ResponseData] и подменяет response.
     *
     * Удаляет все существующие responses, сгенерированные Scramble,
     * и заменяет их на правильную схему из атрибута.
     */
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $reflectionAction = $routeInfo->reflectionAction();
        if ($reflectionAction === null) {
            return;
        }

        $attributes = $reflectionAction->getAttributes(ResponseData::class, ReflectionAttribute::IS_INSTANCEOF);
        if (count($attributes) === 0) {
            return;
        }

        $responseData = $attributes[0]->newInstance();
        $components = $this->openApiTransformer->getComponents();

        $this->ensureSchemaRegistered($responseData->dataClass, $components);

        $dataRef = $this->buildDataReference($responseData->dataClass, $components);
        $responseSchema = $this->buildResponseSchema($responseData, $dataRef);

        $this->replaceAllResponses($operation, $responseData->status, $responseSchema);
    }

    /**
     * Регистрирует схему Data-класса в components/schemas, если её ещё нет.
     *
     * Решает проблему битых $ref-ссылок: когда #[ResponseData] используется
     * с анонимным JsonResource, TypeToSchemaExtension не вызывается,
     * и схема не попадает в components автоматически.
     *
     * @param class-string $dataClass
     */
    private function ensureSchemaRegistered(string $dataClass, Components $components): void
    {
        $schemaName = class_basename($dataClass);

        if ($components->hasSchema($schemaName)) {
            return;
        }

        $resolver = new DataClassSchemaResolver($components);
        $schema = $resolver->resolve($dataClass);

        $components->addSchema($schemaName, Schema::fromType($schema));
    }

    /**
     * Создаёт Reference на схему Data-класса в components/schemas.
     *
     * @param class-string $dataClass
     */
    private function buildDataReference(string $dataClass, Components $components): Reference
    {
        $schemaName = class_basename($dataClass);

        return new Reference('schemas', $schemaName, $components);
    }

    /**
     * Собирает итоговую схему ответа с учётом обёртки, коллекции и пагинации.
     *
     * Для single + wrapped: { "data": $ref }
     * Для collection + wrapped: { "data": [$ref] }
     * Для paginated: { "data": [...], "meta": {...}, "links": {...} }
     * Для unwrapped: $ref или [$ref] напрямую.
     */
    private function buildResponseSchema(ResponseData $responseData, Reference $dataRef): OpenApiType|Reference
    {
        $itemsSchema = $dataRef;

        if ($responseData->isCollection()) {
            $arrayType = new OpenApiArrayType();
            $arrayType->setItems($itemsSchema);
            $itemsSchema = $arrayType;
        }

        if (!$responseData->shouldWrap()) {
            return $itemsSchema;
        }

        $wrapper = new OpenApiObjectType();
        $wrapper->addProperty('data', $itemsSchema);
        $required = ['data'];

        if ($responseData->paginated) {
            $wrapper->addProperty('links', $this->buildPaginationLinks());
            $wrapper->addProperty('meta', $this->buildPaginationMeta());
            $required[] = 'links';
            $required[] = 'meta';
        }

        if ($responseData->cursorPaginated) {
            $wrapper->addProperty('meta', $this->buildCursorPaginationMeta());
            $required[] = 'meta';
        }

        $wrapper->setRequired($required);

        return $wrapper;
    }

    /** Структура links для LengthAwarePaginator: first, last, prev, next. */
    private function buildPaginationLinks(): OpenApiObjectType
    {
        $links = new OpenApiObjectType();
        $links->addProperty('first', (new OpenApiStringType())->nullable(true));
        $links->addProperty('last', (new OpenApiStringType())->nullable(true));
        $links->addProperty('prev', (new OpenApiStringType())->nullable(true));
        $links->addProperty('next', (new OpenApiStringType())->nullable(true));

        return $links;
    }

    /** Структура meta для LengthAwarePaginator: current_page, total, links и др. */
    private function buildPaginationMeta(): OpenApiObjectType
    {
        $linkItem = new OpenApiObjectType();
        $linkItem->addProperty('url', (new OpenApiStringType())->nullable(true));
        $linkItem->addProperty('label', new OpenApiStringType());
        $linkItem->addProperty('active', new OpenApiBooleanType());

        $linksArray = new OpenApiArrayType();
        $linksArray->setItems($linkItem);

        $meta = new OpenApiObjectType();
        $meta->addProperty('current_page', new OpenApiIntegerType());
        $meta->addProperty('from', (new OpenApiIntegerType())->nullable(true));
        $meta->addProperty('last_page', new OpenApiIntegerType());
        $meta->addProperty('links', $linksArray);
        $meta->addProperty('path', new OpenApiStringType());
        $meta->addProperty('per_page', new OpenApiIntegerType());
        $meta->addProperty('to', (new OpenApiIntegerType())->nullable(true));
        $meta->addProperty('total', new OpenApiIntegerType());

        return $meta;
    }

    /** Структура meta для CursorPaginator: path, per_page, next_cursor и др. */
    private function buildCursorPaginationMeta(): OpenApiObjectType
    {
        $meta = new OpenApiObjectType();
        $meta->addProperty('path', new OpenApiStringType());
        $meta->addProperty('per_page', new OpenApiIntegerType());
        $meta->addProperty('next_cursor', (new OpenApiStringType())->nullable(true));
        $meta->addProperty('prev_cursor', (new OpenApiStringType())->nullable(true));
        $meta->addProperty('next_page_url', (new OpenApiStringType())->nullable(true));
        $meta->addProperty('prev_page_url', (new OpenApiStringType())->nullable(true));

        return $meta;
    }

    /**
     * Удаляет все существующие responses и ставит единственный с нашей схемой.
     *
     * Scramble генерирует мусорный 200 response для анонимного JsonResource.
     * Мы заменяем все responses на один правильный с нужным status code.
     */
    private function replaceAllResponses(Operation $operation, int $status, OpenApiType|Reference $schema): void
    {
        $newResponse = Response::make($status)
            ->setContent('application/json', Schema::fromType($schema));

        $operation->responses = [$newResponse];
    }
}
