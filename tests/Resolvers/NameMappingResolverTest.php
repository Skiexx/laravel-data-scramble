<?php

declare(strict_types=1);

use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Skiexx\LaravelDataScramble\Resolvers\DataClassSchemaResolver;
use Skiexx\LaravelDataScramble\Tests\Fixtures\MappedNameData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\SimpleData;

it('maps output names with SnakeCaseMapper', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);
    $schema = $resolver->resolve(MappedNameData::class);

    expect($schema)->toBeInstanceOf(ObjectType::class)
        ->and($schema->properties)->toHaveKeys(['first_name', 'last_name', 'user_age']);
});

it('keeps original names when no mapper is set', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);
    $schema = $resolver->resolve(SimpleData::class);

    expect($schema->properties)->toHaveKeys(['title', 'year']);
});
