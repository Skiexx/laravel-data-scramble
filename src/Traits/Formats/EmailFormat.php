<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Traits\Formats;

/** Трейт OpenAPI-формата: type=string, format=email. */
trait EmailFormat
{
    /** @return array<string, string> */
    public static function openApiType(): array
    {
        return ['type' => 'string', 'format' => 'email'];
    }
}
