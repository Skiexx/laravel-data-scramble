<?php

declare(strict_types=1);

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType as PhpObjectType;
use Skiexx\LaravelDataScramble\Extensions\LaravelDataTypeToSchemaExtension;
use Skiexx\LaravelDataScramble\Tests\Fixtures\CustomSchemaData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\SimpleData;

function makeExtension(): LaravelDataTypeToSchemaExtension
{
    $components = new Components();
    $infer = app(Infer::class);
    $openApi = new OpenApi('3.1.0');
    $openApi->setComponents($components);
    $openApi->setInfo(new InfoObject('Test'));
    $config = new GeneratorConfig();
    $context = new OpenApiContext($openApi, $config);
    $transformer = new TypeTransformer($infer, $context);

    return new LaravelDataTypeToSchemaExtension($infer, $transformer, $components);
}

it('registers extension automatically', function (): void {
    expect(Scramble::$extensions)
        ->toContain(LaravelDataTypeToSchemaExtension::class);
});

it('shouldHandle returns true for Data classes', function (): void {
    $extension = makeExtension();
    $type = new PhpObjectType(SimpleData::class);

    expect($extension->shouldHandle($type))->toBeTrue();
});

it('shouldHandle returns false for non-Data classes', function (): void {
    $extension = makeExtension();
    $type = new PhpObjectType(\stdClass::class);

    expect($extension->shouldHandle($type))->toBeFalse();
});

it('shouldHandle returns true for OpenApiSchema implementations', function (): void {
    $extension = makeExtension();
    $type = new PhpObjectType(CustomSchemaData::class);

    expect($extension->shouldHandle($type))->toBeTrue();
});
