<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Operation;
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
     * Если атрибут не найден — ничего не делает.
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

        $dataSchema = $this->buildDataSchema($responseData);
        $responseSchema = $this->buildResponseSchema($responseData, $dataSchema);

        $this->replaceResponse($operation, $responseData->status, $responseSchema);
    }

    /** Создаёт $ref-ссылку на схему Data-класса в components/schemas. */
    private function buildDataSchema(ResponseData $responseData): OpenApiType
    {
        $components = $this->openApiTransformer->getComponents();

        return ClassBasedReference::create('schemas', $responseData->dataClass, $components);
    }

    /**
     * Собирает итоговую схему ответа с учётом обёртки, коллекции и пагинации.
     *
     * Для single + wrapped: { "data": $ref }
     * Для collection + wrapped: { "data": [$ref] }
     * Для paginated: { "data": [...], "meta": {...}, "links": {...} }
     * Для unwrapped: $ref или [$ref] напрямую.
     */
    private function buildResponseSchema(ResponseData $responseData, OpenApiType $dataSchema): OpenApiType
    {
        $itemsSchema = $dataSchema;

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

    /** Заменяет существующий response с данным status code или добавляет новый. */
    private function replaceResponse(Operation $operation, int $status, OpenApiType $schema): void
    {
        $newResponse = Response::make($status)
            ->setContent('application/json', Schema::fromType($schema));

        $replaced = false;
        foreach ($operation->responses as $i => $existingResponse) {
            if ($existingResponse instanceof Response && $existingResponse->code === $status) {
                $operation->responses[$i] = $newResponse;
                $replaced = true;

                break;
            }
        }

        if (!$replaced) {
            $operation->addResponse($newResponse);
        }
    }
}
