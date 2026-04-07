<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Tests\Fixtures;

use Spatie\LaravelData\Data;

class NestedData extends Data
{
    public function __construct(
        public string $name,
        public SimpleData $child,
    ) {
    }
}
