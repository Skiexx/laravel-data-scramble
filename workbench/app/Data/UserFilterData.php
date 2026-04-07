<?php

declare(strict_types=1);

namespace Workbench\App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class UserFilterData extends Data
{
    public function __construct(
        public ?string $search,
        #[Min(1), Max(100)]
        public int $page = 1,
        public int $perPage = 15,
    ) {
    }
}
