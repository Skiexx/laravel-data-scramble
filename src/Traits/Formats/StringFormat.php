<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Traits\Formats;

/** Трейт OpenAPI-формата: type=string. */
trait StringFormat
{
    /** @return array<string, string> */
    public static function openApiType(): array
    {
        return ['type' => 'string'];
    }
}
