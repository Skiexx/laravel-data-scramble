<?php

declare(strict_types=1);

use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Skiexx\LaravelDataScramble\Support\ValidationAttributeMap;
use Spatie\LaravelData\Attributes\Validation\Alpha;
use Spatie\LaravelData\Attributes\Validation\AlphaDash;
use Spatie\LaravelData\Attributes\Validation\AlphaNumeric;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Attributes\Validation\Uuid;

it('applies min to string type', function (): void {
    $type = new StringType();
    ValidationAttributeMap::apply(new Min(5), $type);

    expect($type->min)->toBe(5);
});

it('applies max to string type', function (): void {
    $type = new StringType();
    ValidationAttributeMap::apply(new Max(100), $type);

    expect($type->max)->toBe(100);
});

it('applies min to integer type', function (): void {
    $type = new IntegerType();
    ValidationAttributeMap::apply(new Min(0), $type);

    expect($type->min)->toBe(0);
});

it('applies max to integer type', function (): void {
    $type = new IntegerType();
    ValidationAttributeMap::apply(new Max(100), $type);

    expect($type->max)->toBe(100);
});

it('applies between to string type', function (): void {
    $type = new StringType();
    ValidationAttributeMap::apply(new Between(3, 255), $type);

    expect($type->min)->toBe(3)
        ->and($type->max)->toBe(255);
});

it('applies email format', function (): void {
    $type = new StringType();
    ValidationAttributeMap::apply(new Email(), $type);

    expect($type->format)->toBe('email');
});

it('applies url format', function (): void {
    $type = new StringType();
    ValidationAttributeMap::apply(new Url(), $type);

    expect($type->format)->toBe('uri');
});

it('applies uuid format', function (): void {
    $type = new StringType();
    ValidationAttributeMap::apply(new Uuid(), $type);

    expect($type->format)->toBe('uuid');
});

it('applies date format', function (): void {
    $type = new StringType();
    ValidationAttributeMap::apply(new Date(), $type);

    expect($type->format)->toBe('date');
});

it('applies regex pattern', function (): void {
    $type = new StringType();
    ValidationAttributeMap::apply(new Regex('/^\d+$/'), $type);

    expect($type->pattern)->toBe('/^\d+$/');
});

it('applies nullable', function (): void {
    $type = new StringType();
    ValidationAttributeMap::apply(new Nullable(), $type);

    expect($type->nullable)->toBeTrue();
});

it('applies alpha pattern', function (): void {
    $type = new StringType();
    ValidationAttributeMap::apply(new Alpha(), $type);

    expect($type->pattern)->toBe('^[a-zA-Z]+$');
});

it('applies alpha dash pattern', function (): void {
    $type = new StringType();
    ValidationAttributeMap::apply(new AlphaDash(), $type);

    expect($type->pattern)->toBe('^[a-zA-Z0-9_-]+$');
});

it('applies alpha numeric pattern', function (): void {
    $type = new StringType();
    ValidationAttributeMap::apply(new AlphaNumeric(), $type);

    expect($type->pattern)->toBe('^[a-zA-Z0-9]+$');
});
