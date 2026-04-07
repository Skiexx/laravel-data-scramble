<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Traits\Formats;

/** Трейт OpenAPI-формата: type=string, format=date-time. */
trait DateFormat
{
    /** @return array<string, string> */
    public static function openApiType(): array
    {
        return ['type' => 'string', 'format' => 'date-time'];
    }
}
