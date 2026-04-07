<?php

declare(strict_types=1);

use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Skiexx\LaravelDataScramble\Resolvers\DataClassSchemaResolver;
use Skiexx\LaravelDataScramble\Tests\Fixtures\CustomSchemaData;

it('resolves schema from OpenApiSchema interface', function (): void {
    $components = new Components();
    $resolver = new DataClassSchemaResolver($components);
    $schema = $resolver->resolve(CustomSchemaData::class);

    expect($schema)->toBeInstanceOf(ObjectType::class)
        ->and($schema->properties)->toHaveKeys(['id', 'value'])
        ->and($schema->required)->toBe(['id', 'value'])
        ->and($schema->properties['id']->format)->toBe('uuid');
});
