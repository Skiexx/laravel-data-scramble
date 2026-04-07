<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Tests\Fixtures;

use Skiexx\LaravelDataScramble\Contracts\OpenApiSchema;

class CustomSchemaData implements OpenApiSchema
{
    public function __construct(
        public string $id,
        public string $value,
    ) {
    }

    public static function openApiSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string', 'format' => 'uuid'],
                'value' => ['type' => 'string'],
            ],
            'required' => ['id', 'value'],
        ];
    }
}
