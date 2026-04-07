<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Tests\Fixtures;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class MappedNameData extends Data
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public int $userAge,
    ) {
    }
}
