<?php

declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Skiexx\LaravelDataScramble\Attributes\ResponseData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\SimpleData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\ValidatedData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\MappedNameData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\NestedData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\EnumData;
use Skiexx\LaravelDataScramble\Tests\Fixtures\NullableData;

function generateOpenApi(): array
{
    $config = Scramble::getGeneratorConfig('default');
    $config->routes(fn (\Illuminate\Routing\Route $route) => str_starts_with($route->uri, 'api'));

    $generator = app(Generator::class);

    return $generator($config);
}

// ─────────────────────────────────────────────────────────
// Data как возвращаемый тип контроллера (response)
// ─────────────────────────────────────────────────────────

it('generates schema for route returning Data class', function (): void {
    Route::get('api/simple', fn (): SimpleData => SimpleData::from(['title' => 'Test', 'year' => 2024]));

    $doc = generateOpenApi();

    expect($doc['paths'])->toHaveKey('/simple');

    $response = $doc['paths']['/simple']['get']['responses']['200'] ?? null;
    expect($response)->not->toBeNull();

    $schema = $response['content']['application/json']['schema'] ?? null;
    expect($schema)->not->toBeNull()
        ->and($schema)->toHaveKey('$ref');

    // Проверяем что component schema создан
    $refName = str_replace('#/components/schemas/', '', $schema['$ref']);
    expect($doc['components']['schemas'])->toHaveKey($refName);

    $componentSchema = $doc['components']['schemas'][$refName];
    expect($componentSchema['type'])->toBe('object')
        ->and($componentSchema['properties'])->toHaveKeys(['title', 'year'])
        ->and($componentSchema['properties']['title']['type'])->toBe('string')
        ->and($componentSchema['properties']['year']['type'])->toBe('integer')
        ->and($componentSchema['required'])->toContain('title', 'year');
});

it('generates schema with validation constraints', function (): void {
    Route::post('api/validated', fn (ValidatedData $data): array => ['ok' => true]);

    $doc = generateOpenApi();

    // ValidatedData должен быть в components/schemas
    $schemas = $doc['components']['schemas'] ?? [];
    $validatedSchema = null;
    foreach ($schemas as $name => $schema) {
        if (str_contains($name, 'ValidatedData')) {
            $validatedSchema = $schema;

            break;
        }
    }

    expect($validatedSchema)->not->toBeNull()
        ->and($validatedSchema['properties']['title']['minLength'])->toBe(3)
        ->and($validatedSchema['properties']['title']['maxLength'])->toBe(255)
        ->and($validatedSchema['properties']['email']['format'])->toBe('email')
        ->and($validatedSchema['properties']['rating']['minimum'])->toBe(0)
        ->and($validatedSchema['properties']['rating']['maximum'])->toBe(100);
});

it('generates schema with nested data as $ref', function (): void {
    Route::get('api/nested', fn (): NestedData => NestedData::from(['name' => 'Test', 'child' => ['title' => 'Child', 'year' => 2024]]));

    $doc = generateOpenApi();

    $schemas = $doc['components']['schemas'] ?? [];

    // Найдём NestedData
    $nestedSchema = null;
    foreach ($schemas as $name => $schema) {
        if (str_contains($name, 'NestedData')) {
            $nestedSchema = $schema;

            break;
        }
    }

    expect($nestedSchema)->not->toBeNull()
        ->and($nestedSchema['properties']['name']['type'])->toBe('string')
        ->and($nestedSchema['properties']['child'])->toHaveKey('$ref');
});

it('generates schema with enum values', function (): void {
    Route::get('api/enum', fn (): EnumData => EnumData::from(['name' => 'Test', 'status' => 'active', 'priority' => 1]));

    $doc = generateOpenApi();

    $schemas = $doc['components']['schemas'] ?? [];
    $enumSchema = null;
    foreach ($schemas as $name => $schema) {
        if (str_contains($name, 'EnumData')) {
            $enumSchema = $schema;

            break;
        }
    }

    expect($enumSchema)->not->toBeNull()
        ->and($enumSchema['properties']['status']['enum'])->toBe(['active', 'inactive', 'pending'])
        ->and($enumSchema['properties']['priority']['enum'])->toBe([1, 2, 3]);
});

it('generates schema with nullable properties', function (): void {
    Route::get('api/nullable', fn (): NullableData => NullableData::from(['name' => 'Test', 'nickname' => null, 'age' => null]));

    $doc = generateOpenApi();

    $schemas = $doc['components']['schemas'] ?? [];
    $nullableSchema = null;
    foreach ($schemas as $name => $schema) {
        if (str_contains($name, 'NullableData')) {
            $nullableSchema = $schema;

            break;
        }
    }

    expect($nullableSchema)->not->toBeNull()
        ->and($nullableSchema['properties']['nickname']['type'])->toContain('null')
        ->and($nullableSchema['properties']['age']['type'])->toContain('null')
        ->and($nullableSchema['required'])->toBe(['name']);
});

it('generates schema with mapped output names', function (): void {
    Route::get('api/mapped', fn (): MappedNameData => MappedNameData::from(['firstName' => 'John', 'lastName' => 'Doe', 'userAge' => 30]));

    $doc = generateOpenApi();

    $schemas = $doc['components']['schemas'] ?? [];
    $mappedSchema = null;
    foreach ($schemas as $name => $schema) {
        if (str_contains($name, 'MappedNameData')) {
            $mappedSchema = $schema;

            break;
        }
    }

    expect($mappedSchema)->not->toBeNull()
        ->and($mappedSchema['properties'])->toHaveKeys(['first_name', 'last_name', 'user_age']);
});

// ─────────────────────────────────────────────────────────
// Data как входной параметр контроллера (request)
// ─────────────────────────────────────────────────────────

it('generates query parameters for Data in GET route', function (): void {
    Route::get('api/search', fn (SimpleData $data): array => ['ok' => true]);

    $doc = generateOpenApi();

    expect($doc['paths'])->toHaveKey('/search');

    $parameters = $doc['paths']['/search']['get']['parameters'] ?? [];
    $paramNames = array_column($parameters, 'name');

    expect($paramNames)->toContain('title', 'year');

    $titleParam = collect($parameters)->firstWhere('name', 'title');
    $yearParam = collect($parameters)->firstWhere('name', 'year');

    expect($titleParam['in'])->toBe('query')
        ->and($yearParam['in'])->toBe('query')
        ->and($titleParam['required'])->toBeTrue()
        ->and($yearParam['required'])->toBeTrue()
        ->and($titleParam['schema']['type'])->toBe('string')
        ->and($yearParam['schema']['type'])->toBe('integer');
});

it('generates request body for Data in POST route', function (): void {
    Route::post('api/create', fn (ValidatedData $data): array => ['ok' => true]);

    $doc = generateOpenApi();

    expect($doc['paths'])->toHaveKey('/create');

    $requestBody = $doc['paths']['/create']['post']['requestBody'] ?? null;
    expect($requestBody)->not->toBeNull();

    $bodySchema = $requestBody['content']['application/json']['schema'] ?? null;
    expect($bodySchema)->not->toBeNull();
});

it('generates query params with validation constraints for GET', function (): void {
    Route::get('api/validated-get', fn (ValidatedData $data): array => ['ok' => true]);

    $doc = generateOpenApi();

    $parameters = $doc['paths']['/validated-get']['get']['parameters'] ?? [];

    $titleParam = collect($parameters)->firstWhere('name', 'title');
    $emailParam = collect($parameters)->firstWhere('name', 'email');

    expect($titleParam)->not->toBeNull()
        ->and($titleParam['schema']['minLength'])->toBe(3)
        ->and($titleParam['schema']['maxLength'])->toBe(255)
        ->and($emailParam['schema']['format'])->toBe('email');
});

it('generates nullable query params as not required', function (): void {
    Route::get('api/nullable-get', fn (NullableData $data): array => ['ok' => true]);

    $doc = generateOpenApi();

    $parameters = $doc['paths']['/nullable-get']['get']['parameters'] ?? [];

    $nameParam = collect($parameters)->firstWhere('name', 'name');
    $nicknameParam = collect($parameters)->firstWhere('name', 'nickname');

    expect($nameParam['required'])->toBeTrue()
        ->and($nicknameParam['required'] ?? false)->toBeFalse();
});

// ─────────────────────────────────────────────────────────
// #[ResponseData] атрибут с JsonResource
// ─────────────────────────────────────────────────────────

it('generates response schema from #[ResponseData] attribute', function (): void {
    Scramble::configure('default')
        ->routes(fn (\Illuminate\Routing\Route $route) => str_starts_with($route->uri, 'api'));

    Route::get('api/users/{id}', [ResponseDataIntegrationController::class, 'show']);

    $doc = generateOpenApi();

    expect($doc['paths'])->toHaveKey('/users/{id}');

    $response = $doc['paths']['/users/{id}']['get']['responses']['200'] ?? null;
    expect($response)->not->toBeNull();

    $schema = $response['content']['application/json']['schema'] ?? null;
    expect($schema)->not->toBeNull()
        ->and($schema['type'])->toBe('object')
        ->and($schema['properties'])->toHaveKey('data')
        ->and($schema['required'])->toContain('data');
});

it('generates collection response from #[ResponseData] with collection flag', function (): void {
    Scramble::configure('default')
        ->routes(fn (\Illuminate\Routing\Route $route) => str_starts_with($route->uri, 'api'));

    Route::get('api/users-list', [ResponseDataIntegrationController::class, 'index']);

    $doc = generateOpenApi();

    $response = $doc['paths']['/users-list']['get']['responses']['200'] ?? null;
    $schema = $response['content']['application/json']['schema'] ?? null;

    expect($schema['properties']['data']['type'])->toBe('array')
        ->and($schema['properties']['data']['items'])->toHaveKey('$ref');
});

it('generates paginated response with meta and links', function (): void {
    Scramble::configure('default')
        ->routes(fn (\Illuminate\Routing\Route $route) => str_starts_with($route->uri, 'api'));

    Route::get('api/users-paginated', [ResponseDataIntegrationController::class, 'paginated']);

    $doc = generateOpenApi();

    $response = $doc['paths']['/users-paginated']['get']['responses']['200'] ?? null;
    $schema = $response['content']['application/json']['schema'] ?? null;

    expect($schema['type'])->toBe('object')
        ->and($schema['required'])->toContain('data', 'links', 'meta')
        ->and($schema['properties'])->toHaveKeys(['data', 'links', 'meta'])
        ->and($schema['properties']['data']['type'])->toBe('array')
        ->and($schema['properties']['meta']['properties'])->toHaveKeys(['current_page', 'total', 'per_page'])
        ->and($schema['properties']['links']['properties'])->toHaveKeys(['first', 'last', 'prev', 'next']);
});

it('generates response with custom status code 201', function (): void {
    Scramble::configure('default')
        ->routes(fn (\Illuminate\Routing\Route $route) => str_starts_with($route->uri, 'api'));

    Route::post('api/users-create', [ResponseDataIntegrationController::class, 'store']);

    $doc = generateOpenApi();

    $responses = $doc['paths']['/users-create']['post']['responses'] ?? [];
    expect($responses)->toHaveKey('201');
});

// ─────────────────────────────────────────────────────────
// Контроллер-фикстура для интеграционных тестов
// ─────────────────────────────────────────────────────────

class ResponseDataIntegrationController
{
    #[ResponseData(SimpleData::class)]
    public function show(string $id): JsonResource
    {
        return new JsonResource(['title' => 'Test', 'year' => 2024]);
    }

    #[ResponseData(SimpleData::class, collection: true)]
    public function index(): JsonResource
    {
        return JsonResource::collection([]);
    }

    #[ResponseData(SimpleData::class, paginated: true)]
    public function paginated(): JsonResource
    {
        return JsonResource::collection([]);
    }

    #[ResponseData(SimpleData::class, status: 201)]
    public function store(SimpleData $data): JsonResource
    {
        return new JsonResource(['title' => 'Test', 'year' => 2024]);
    }
}
