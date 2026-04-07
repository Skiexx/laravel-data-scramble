<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Attributes;

use Attribute;

/**
 * Атрибут для указания типа ответа на методе контроллера.
 *
 * Используется когда контроллер возвращает анонимный JsonResource,
 * и Scramble не может определить реальный тип данных внутри.
 *
 * Поддерживает: одиночный объект, коллекцию, LengthAwarePaginator,
 * CursorPaginator, кастомный status code и управление data-обёрткой.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ResponseData
{
    /**
     * @param  class-string  $dataClass  FQCN Data-класса, описывающего структуру ответа
     * @param  bool  $collection  Ответ содержит массив объектов
     * @param  bool  $paginated  Формат LengthAwarePaginator (data + meta + links)
     * @param  bool  $cursorPaginated  Формат CursorPaginator (data + meta)
     * @param  int  $status  HTTP status code ответа (например 201 для POST Create)
     * @param  bool  $wrapped  Обёртка { "data": ... }, для paginated всегда true
     */
    public function __construct(
        public readonly string $dataClass,
        public readonly bool $collection = false,
        public readonly bool $paginated = false,
        public readonly bool $cursorPaginated = false,
        public readonly int $status = 200,
        public readonly bool $wrapped = true,
    ) {
    }

    /** Является ли ответ коллекцией (включая paginated и cursorPaginated). */
    public function isCollection(): bool
    {
        return $this->collection || $this->paginated || $this->cursorPaginated;
    }

    /**
     * Нужно ли оборачивать ответ в { "data": ... }.
     *
     * Для paginated и cursorPaginated — всегда true,
     * т.к. это стандартный формат ответа Laravel-пагинатора.
     */
    public function shouldWrap(): bool
    {
        if ($this->paginated || $this->cursorPaginated) {
            return true;
        }

        return $this->wrapped;
    }
}
