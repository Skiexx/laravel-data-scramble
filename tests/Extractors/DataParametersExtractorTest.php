<?php

declare(strict_types=1);

use Dedoc\Scramble\Support\Generator\Components;
use Skiexx\LaravelDataScramble\Extractors\DataParametersExtractor;
use Skiexx\LaravelDataScramble\Tests\Fixtures\RouteParamData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\SimpleData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\ValidatedData;

it('creates extractor instance', function (): void {
    $components = new Components();
    $extractor = new DataParametersExtractor($components);

    expect($extractor)->toBeInstanceOf(DataParametersExtractor::class);
});

it('detects FromRouteParameter attribute on data property', function (): void {
    $config = app(\Spatie\LaravelData\Support\DataConfig::class);
    $dataClass = $config->getDataClass(RouteParamData::class);

    $userIdProperty = $dataClass->properties->get('userId');

    expect($userIdProperty->attributes->has(\Spatie\LaravelData\Attributes\FromRouteParameter::class))->toBeTrue();
});

it('detects FromRouteParameter route name', function (): void {
    $config = app(\Spatie\LaravelData\Support\DataConfig::class);
    $dataClass = $config->getDataClass(RouteParamData::class);

    $userIdProperty = $dataClass->properties->get('userId');
    $attr = $userIdProperty->attributes->first(\Spatie\LaravelData\Attributes\FromRouteParameter::class);

    expect($attr)->not->toBeNull()
        ->and($attr->routeParameter)->toBe('user');
});

it('identifies Data class parameters correctly', function (): void {
    $reflectionMethod = new ReflectionMethod(DataExtractorTestController::class, 'index');
    $params = $reflectionMethod->getParameters();

    $dataParam = null;
    foreach ($params as $param) {
        $type = $param->getType();
        if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), \Spatie\LaravelData\Contracts\BaseData::class)) {
            $dataParam = $param;

            break;
        }
    }

    expect($dataParam)->not->toBeNull()
        ->and($dataParam->getType()->getName())->toBe(SimpleData::class);
});

it('identifies non-Data class parameters correctly', function (): void {
    $reflectionMethod = new ReflectionMethod(DataExtractorTestController::class, 'noData');
    $params = $reflectionMethod->getParameters();

    $hasData = false;
    foreach ($params as $param) {
        $type = $param->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && is_subclass_of($type->getName(), \Spatie\LaravelData\Contracts\BaseData::class)) {
            $hasData = true;
        }
    }

    expect($hasData)->toBeFalse();
});

class DataExtractorTestController
{
    public function index(SimpleData $data): array
    {
        return [];
    }

    public function store(ValidatedData $data): array
    {
        return [];
    }

    public function noData(string $id): array
    {
        return [];
    }

    public function withRoute(RouteParamData $data): array
    {
        return [];
    }
}
