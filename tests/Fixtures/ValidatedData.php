<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Tests\Fixtures;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class ValidatedData extends Data
{
    public function __construct(
        #[Min(3), Max(255)]
        public string $title,
        #[Email]
        public string $email,
        #[Min(0), Max(100)]
        public int $rating,
    ) {
    }
}
