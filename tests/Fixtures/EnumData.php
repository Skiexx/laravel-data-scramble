<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Tests\Fixtures;

use Spatie\LaravelData\Data;

class EnumData extends Data
{
    public function __construct(
        public string $name,
        public TestStringEnum $status,
        public TestIntEnum $priority,
    ) {
    }
}
