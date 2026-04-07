<?php

declare(strict_types=1);

namespace Workbench\App\Data;

enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';
    case Moderator = 'moderator';
}
