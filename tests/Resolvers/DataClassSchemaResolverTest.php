<?php

declare(strict_types=1);

use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Skiexx\LaravelDataScramble\Resolvers\DataClassSchemaResolver;
use Skiexx\LaravelDataScramble\Tests\Fixtures\DefaultValueData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\NullableData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\SimpleData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\ValidatedData;

it('resolves simple data class', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);

    $schema = $resolver->resolve(SimpleData::class);

    expect($schema)
        ->toBeInstanceOf(ObjectType::class)
        ->and($schema->properties)->toHaveKeys(['title', 'year'])
        ->and($schema->properties['title'])->toBeInstanceOf(StringType::class)
        ->and($schema->properties['year'])->toBeInstanceOf(IntegerType::class)
        ->and($schema->required)->toBe(['title', 'year']);
});

it('resolves nullable properties', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);

    $schema = $resolver->resolve(NullableData::class);

    expect($schema)->toBeInstanceOf(ObjectType::class)
        ->and($schema->properties['nickname']->nullable)->toBeTrue()
        ->and($schema->properties['age']->nullable)->toBeTrue()
        ->and($schema->required)->toBe(['name']);
});

it('excludes properties with default values from required', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);

    $schema = $resolver->resolve(DefaultValueData::class);

    expect($schema)->toBeInstanceOf(ObjectType::class)
        ->and($schema->required)->toBe(['name']);
});

it('applies validation constraints', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);

    $schema = $resolver->resolve(ValidatedData::class);

    expect($schema)->toBeInstanceOf(ObjectType::class)
        ->and($schema->properties['title'])->toBeInstanceOf(StringType::class)
        ->and($schema->properties['title']->min)->toBe(3)
        ->and($schema->properties['title']->max)->toBe(255)
        ->and($schema->properties['email']->format)->toBe('email')
        ->and($schema->properties['rating'])->toBeInstanceOf(IntegerType::class)
        ->and($schema->properties['rating']->min)->toBe(0)
        ->and($schema->properties['rating']->max)->toBe(100);
});
