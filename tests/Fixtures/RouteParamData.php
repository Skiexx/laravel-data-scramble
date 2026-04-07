<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Tests\Fixtures;

use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class RouteParamData extends Data
{
    public function __construct(
        #[FromRouteParameter('user')]
        public int $userId,
        #[Min(1), Max(100)]
        public int $page,
        public ?string $search,
    ) {
    }
}
