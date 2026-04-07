<?php

declare(strict_types=1);

namespace Workbench\App\Data;

use Skiexx\LaravelDataScramble\Contracts\OpenApiSchema;
use Skiexx\LaravelDataScramble\Traits\Formats\UuidFormat;
use Stringable;

class UuidValue implements Stringable, OpenApiSchema
{
    use UuidFormat;

    public function __construct(
        public readonly string $value,
    ) {
    }

    public static function openApiSchema(): array
    {
        return static::openApiType();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
