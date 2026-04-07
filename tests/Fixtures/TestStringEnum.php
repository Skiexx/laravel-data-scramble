<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Tests\Fixtures;

enum TestStringEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
}
