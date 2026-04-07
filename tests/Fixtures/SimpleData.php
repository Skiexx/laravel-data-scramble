<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Tests\Fixtures;

use Spatie\LaravelData\Data;

class SimpleData extends Data
{
    public function __construct(
        public string $title,
        public int $year,
    ) {
    }
}
