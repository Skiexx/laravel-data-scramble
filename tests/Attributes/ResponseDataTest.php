<?php

declare(strict_types=1);

use Skiexx\LaravelDataScramble\Attributes\ResponseData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\SimpleData;

it('creates attribute with default parameters', function (): void {
    $attr = new ResponseData(SimpleData::class);

    expect($attr->dataClass)->toBe(SimpleData::class)
        ->and($attr->collection)->toBeFalse()
        ->and($attr->paginated)->toBeFalse()
        ->and($attr->cursorPaginated)->toBeFalse()
        ->and($attr->status)->toBe(200)
        ->and($attr->wrapped)->toBeTrue();
});

it('creates attribute with custom parameters', function (): void {
    $attr = new ResponseData(
        SimpleData::class,
        collection: true,
        status: 201,
        wrapped: false,
    );

    expect($attr->collection)->toBeTrue()
        ->and($attr->status)->toBe(201)
        ->and($attr->wrapped)->toBeFalse();
});

it('isCollection returns true for collection', function (): void {
    expect((new ResponseData(SimpleData::class, collection: true))->isCollection())->toBeTrue();
});

it('isCollection returns true for paginated', function (): void {
    expect((new ResponseData(SimpleData::class, paginated: true))->isCollection())->toBeTrue();
});

it('isCollection returns true for cursorPaginated', function (): void {
    expect((new ResponseData(SimpleData::class, cursorPaginated: true))->isCollection())->toBeTrue();
});

it('isCollection returns false for single', function (): void {
    expect((new ResponseData(SimpleData::class))->isCollection())->toBeFalse();
});

it('shouldWrap returns true by default', function (): void {
    expect((new ResponseData(SimpleData::class))->shouldWrap())->toBeTrue();
});

it('shouldWrap returns false when wrapped is false', function (): void {
    expect((new ResponseData(SimpleData::class, wrapped: false))->shouldWrap())->toBeFalse();
});

it('shouldWrap returns true for paginated even when wrapped is false', function (): void {
    expect((new ResponseData(SimpleData::class, paginated: true, wrapped: false))->shouldWrap())->toBeTrue();
});

it('shouldWrap returns true for cursorPaginated even when wrapped is false', function (): void {
    expect((new ResponseData(SimpleData::class, cursorPaginated: true, wrapped: false))->shouldWrap())->toBeTrue();
});
