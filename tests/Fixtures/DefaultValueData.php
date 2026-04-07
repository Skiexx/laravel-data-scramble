<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Tests\Fixtures;

use Spatie\LaravelData\Data;

class DefaultValueData extends Data
{
    public function __construct(
        public string $name,
        public string $status = 'active',
        public int $count = 0,
    ) {
    }
}
