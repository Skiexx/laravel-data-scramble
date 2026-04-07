<?php

declare(strict_types=1);

namespace Workbench\App\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class CreateUserData extends Data
{
    public function __construct(
        #[Min(2), Max(255)]
        public string $firstName,
        #[Min(2), Max(255)]
        public string $lastName,
        #[Email]
        public string $email,
        public ?string $phone,
    ) {
    }
}
