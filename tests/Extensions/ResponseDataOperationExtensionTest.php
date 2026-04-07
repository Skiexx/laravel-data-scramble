<?php

declare(strict_types=1);

use Skiexx\LaravelDataScramble\Attributes\ResponseData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\SimpleData;

it('builds single wrapped response schema', function (): void {
    $attr = new ResponseData(SimpleData::class);

    expect($attr->shouldWrap())->toBeTrue()
        ->and($attr->isCollection())->toBeFalse();
});

it('builds collection wrapped response schema', function (): void {
    $attr = new ResponseData(SimpleData::class, collection: true);

    expect($attr->shouldWrap())->toBeTrue()
        ->and($attr->isCollection())->toBeTrue();
});

it('builds paginated response with meta and links', function (): void {
    $attr = new ResponseData(SimpleData::class, paginated: true);

    expect($attr->paginated)->toBeTrue()
        ->and($attr->shouldWrap())->toBeTrue()
        ->and($attr->isCollection())->toBeTrue();
});

it('builds cursor paginated response with meta', function (): void {
    $attr = new ResponseData(SimpleData::class, cursorPaginated: true);

    expect($attr->cursorPaginated)->toBeTrue()
        ->and($attr->shouldWrap())->toBeTrue()
        ->and($attr->isCollection())->toBeTrue();
});

it('uses custom status code', function (): void {
    $attr = new ResponseData(SimpleData::class, status: 201);

    expect($attr->status)->toBe(201);
});

it('builds unwrapped single response', function (): void {
    $attr = new ResponseData(SimpleData::class, wrapped: false);

    expect($attr->shouldWrap())->toBeFalse()
        ->and($attr->isCollection())->toBeFalse();
});

it('builds unwrapped collection response', function (): void {
    $attr = new ResponseData(SimpleData::class, collection: true, wrapped: false);

    expect($attr->shouldWrap())->toBeFalse()
        ->and($attr->isCollection())->toBeTrue();
});

it('paginated always wraps even when wrapped is false', function (): void {
    $attr = new ResponseData(SimpleData::class, paginated: true, wrapped: false);

    expect($attr->shouldWrap())->toBeTrue();
});

it('can be read from reflection method', function (): void {
    $reflection = new ReflectionMethod(ResponseDataTestController::class, 'index');
    $attributes = $reflection->getAttributes(ResponseData::class);

    expect($attributes)->toHaveCount(1);

    $attr = $attributes[0]->newInstance();

    expect($attr->dataClass)->toBe(SimpleData::class)
        ->and($attr->collection)->toBeTrue()
        ->and($attr->status)->toBe(200);
});

it('can read status 201 from reflection', function (): void {
    $reflection = new ReflectionMethod(ResponseDataTestController::class, 'store');
    $attributes = $reflection->getAttributes(ResponseData::class);
    $attr = $attributes[0]->newInstance();

    expect($attr->dataClass)->toBe(SimpleData::class)
        ->and($attr->status)->toBe(201)
        ->and($attr->wrapped)->toBeTrue();
});

it('can read paginated from reflection', function (): void {
    $reflection = new ReflectionMethod(ResponseDataTestController::class, 'paginated');
    $attributes = $reflection->getAttributes(ResponseData::class);
    $attr = $attributes[0]->newInstance();

    expect($attr->paginated)->toBeTrue()
        ->and($attr->shouldWrap())->toBeTrue();
});

// Test controller fixture
class ResponseDataTestController
{
    #[ResponseData(SimpleData::class, collection: true)]
    public function index(): \Illuminate\Http\Resources\Json\JsonResource
    {
        return \Illuminate\Http\Resources\Json\JsonResource::collection([]);
    }

    #[ResponseData(SimpleData::class, status: 201)]
    public function store(): \Illuminate\Http\Resources\Json\JsonResource
    {
        return new \Illuminate\Http\Resources\Json\JsonResource([]);
    }

    #[ResponseData(SimpleData::class, paginated: true)]
    public function paginated(): \Illuminate\Http\Resources\Json\JsonResource
    {
        return \Illuminate\Http\Resources\Json\JsonResource::collection([]);
    }
}
