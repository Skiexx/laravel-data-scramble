<?php

declare(strict_types=1);

use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Skiexx\LaravelDataScramble\Resolvers\DataClassSchemaResolver;
use Skiexx\LaravelDataScramble\Tests\Fixtures\CollectionData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\EnumData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\NestedData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\SimpleData;

it('resolves string type', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);
    $schema = $resolver->resolve(SimpleData::class);

    expect($schema->properties['title'])->toBeInstanceOf(StringType::class);
});

it('resolves integer type', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);
    $schema = $resolver->resolve(SimpleData::class);

    expect($schema->properties['year'])->toBeInstanceOf(IntegerType::class);
});

it('resolves nested data as reference', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);
    $schema = $resolver->resolve(NestedData::class);

    expect($schema->properties['name'])->toBeInstanceOf(StringType::class)
        ->and($schema->properties['child'])->toBeInstanceOf(Reference::class);
});

it('resolves enum with string backing type', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);
    $schema = $resolver->resolve(EnumData::class);

    expect($schema->properties['status'])->toBeInstanceOf(StringType::class)
        ->and($schema->properties['status']->enum)->toBe(['active', 'inactive', 'pending']);
});

it('resolves enum with int backing type', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);
    $schema = $resolver->resolve(EnumData::class);

    expect($schema->properties['priority'])->toBeInstanceOf(IntegerType::class)
        ->and($schema->properties['priority']->enum)->toBe([1, 2, 3]);
});

it('resolves data collection as array type with reference items', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);
    $schema = $resolver->resolve(CollectionData::class);

    expect($schema->properties['items'])->toBeInstanceOf(ArrayType::class)
        ->and($schema->properties['items']->items)->toBeInstanceOf(Reference::class);
});
