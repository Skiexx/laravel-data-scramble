<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * Удаляет указанный префикс из пути маршрута в OpenAPI.
 *
 * Решает проблему когда роуты имеют технический префикс (web/, v1/ и т.д.),
 * который не нужен в публичной документации.
 * Настраивается через config('laravel-data-scramble.strip_prefix').
 */
class StripRoutePrefixExtension extends OperationExtension
{
    /** Обрезает префикс из пути операции если настроен strip_prefix. */
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $prefix = config('laravel-data-scramble.strip_prefix');

        if ($prefix === null || $prefix === '') {
            return;
        }

        $prefix = trim($prefix, '/');

        $path = $operation->path;
        if (Str::startsWith($path, $prefix)) {
            $operation->setPath(
                '/' . ltrim(Str::replaceFirst($prefix, '', $path), '/')
            );
        }
    }
}
