<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Tests\Fixtures;

use Spatie\LaravelData\Data;

class NullableData extends Data
{
    public function __construct(
        public string $name,
        public ?string $nickname,
        public ?int $age,
    ) {
    }
}
