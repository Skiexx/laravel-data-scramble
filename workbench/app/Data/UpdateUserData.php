<?php

declare(strict_types=1);

namespace Workbench\App\Data;

use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class UpdateUserData extends Data
{
    public function __construct(
        #[FromRouteParameter('user')]
        public int $userId,
        #[Min(2), Max(255)]
        public string $firstName,
        #[Min(2), Max(255)]
        public string $lastName,
        #[Email]
        public string $email,
    ) {
    }
}
