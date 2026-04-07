<?php

declare(strict_types=1);

namespace Workbench\App\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class UserData extends Data
{
    public function __construct(
        public int $id,
        #[Min(2), Max(255)]
        public string $firstName,
        #[Min(2), Max(255)]
        public string $lastName,
        #[Email]
        public string $email,
        public ?string $phone,
        public UserRole $role,
    ) {
    }
}
