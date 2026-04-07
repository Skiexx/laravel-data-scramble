<?php

declare(strict_types=1);

namespace Workbench\App\Data;

use Spatie\LaravelData\Data;

class AddressData extends Data
{
    public function __construct(
        public string $city,
        public string $street,
        public ?string $zip,
    ) {
    }
}
