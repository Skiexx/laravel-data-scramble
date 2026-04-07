<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Tests\Fixtures;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class CollectionData extends Data
{
    /**
     * @param SimpleData[] $items
     */
    public function __construct(
        public string $name,
        #[DataCollectionOf(SimpleData::class)]
        public array $items,
    ) {
    }
}
