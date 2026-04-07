# Laravel Data Scramble

> **[Русская версия](README.md)**

A free extension for [dedoc/scramble](https://github.com/dedoc/scramble) that automatically generates OpenAPI schemas from [spatie/laravel-data](https://github.com/spatie/laravel-data) classes.

The package analyzes your `Data`, `Resource`, and `Dto` classes used as controller input parameters (instead of `FormRequest`) and return types (instead of `JsonResource`), building complete OpenAPI documentation for each route.

## Features

- Automatic parsing of `Data`, `Resource`, `Dto` classes
- Support for all PHP scalar types: `string`, `int`, `float`, `bool`, `array`
- Support for `Carbon`, `DateTime`, `DateTimeImmutable`, `DateInterval`
- Support for backed enums (`string` and `int`)
- Nested Data objects with automatic `$ref` links in `#/components/schemas/`
- Data object collections (`Data[]`, `#[DataCollectionOf]`)
- Mapping of 20+ spatie/laravel-data validation attributes to OpenAPI constraints
- Property renaming support via `#[MapOutputName]`, `#[MapInputName]`, `#[MapName]`
- Support for `SnakeCaseMapper`, `CamelCaseMapper`, and custom mappers
- Handling of `nullable`, `optional`, `default`, `computed`, `hidden`, `lazy` properties
- `OpenApiSchema` interface for fully manual schema definition of any class
- `HasOpenApiSchema` trait for auto-generating schema from Data class
- Format traits (`StringFormat`, `UuidFormat`, `EmailFormat`, etc.)
- `#[ResponseData]` attribute for describing anonymous `JsonResource` responses
- Pagination support (LengthAwarePaginator, CursorPaginator) with meta/links

## Requirements

- PHP ^8.4
- Laravel ^12.0
- spatie/laravel-data ^4.0
- dedoc/scramble ^0.13

## Installation

```bash
composer require skiexx/laravel-data-scramble
```

The service provider is registered automatically via Laravel auto-discovery. The Scramble extension is also registered automatically when the package is loaded.

### Publishing configuration (optional)

```bash
php artisan vendor:publish --tag="laravel-data-scramble-config"
```

The configuration file will be created at `config/laravel-data-scramble.php`.

## Quick start

After installation, the package works out of the box. No additional configuration is required.

### Controller example

```php
use App\Data\UserData;
use App\Data\CreateUserData;

class UserController
{
    public function index(): UserData
    {
        return UserData::from(User::first());
    }

    public function store(CreateUserData $data): UserData
    {
        $user = User::create($data->toArray());
        return UserData::from($user);
    }
}
```

### Data class example

```php
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;

class CreateUserData extends Data
{
    public function __construct(
        #[Min(2), Max(255)]
        public string $name,

        #[Email]
        public string $email,

        #[Min(8)]
        public string $password,

        public ?string $phone,
    ) {
    }
}
```

This class will automatically generate an OpenAPI schema:

```yaml
CreateUserData:
  type: object
  required:
    - name
    - email
    - password
  properties:
    name:
      type: string
      minLength: 2
      maxLength: 255
    email:
      type: string
      format: email
    password:
      type: string
      minLength: 8
    phone:
      type:
        - string
        - "null"
```

## Configuration

```php
// config/laravel-data-scramble.php

return [
    // Automatic extension registration in Scramble.
    // Set to false to register manually.
    'auto_register' => true,

    // Skip properties with #[Hidden] attribute.
    'skip_hidden' => true,

    // Lazy properties are treated as optional (not included in required).
    'lazy_as_optional' => true,

    // Computed properties get readOnly: true.
    'computed_as_readonly' => true,
];
```

## Supported types

### Scalar types

| PHP type  | OpenAPI type              |
|-----------|---------------------------|
| `string`  | `type: string`            |
| `int`     | `type: integer`           |
| `float`   | `type: number`            |
| `bool`    | `type: boolean`           |
| `array`   | `type: array`             |
| `mixed`   | no constraints            |

### Dates

| PHP type                   | OpenAPI                              |
|----------------------------|--------------------------------------|
| `Carbon\Carbon`            | `type: string, format: date-time`    |
| `Carbon\CarbonImmutable`   | `type: string, format: date-time`    |
| `DateTime`                 | `type: string, format: date-time`    |
| `DateTimeImmutable`        | `type: string, format: date-time`    |
| `DateTimeInterface`        | `type: string, format: date-time`    |
| `DateInterval`             | `type: string, format: duration`     |

### Enums

Backed enums are automatically converted to the corresponding type with allowed values:

```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class UserData extends Data
{
    public function __construct(
        public string $name,
        public Status $status,
    ) {
    }
}
```

Result:

```yaml
status:
  type: string
  enum:
    - active
    - inactive
```

For `int`-backed enums, `type: integer` is used.

### Nested Data objects

Nested objects automatically create `$ref` links in `#/components/schemas/`:

```php
class AddressData extends Data
{
    public function __construct(
        public string $city,
        public string $street,
    ) {
    }
}

class UserData extends Data
{
    public function __construct(
        public string $name,
        public AddressData $address,
    ) {
    }
}
```

Result:

```yaml
UserData:
  type: object
  required:
    - name
    - address
  properties:
    name:
      type: string
    address:
      $ref: '#/components/schemas/AddressData'
```

### Data object collections

```php
class TeamData extends Data
{
    /**
     * @param UserData[] $members
     */
    public function __construct(
        public string $name,
        #[DataCollectionOf(UserData::class)]
        public array $members,
    ) {
    }
}
```

Result:

```yaml
members:
  type: array
  items:
    $ref: '#/components/schemas/UserData'
```

## Validation attribute mapping

The package automatically converts spatie/laravel-data validation attributes to corresponding OpenAPI constraints:

### Size constraints

| Attribute        | string              | integer/number  | array          |
|------------------|---------------------|-----------------|----------------|
| `#[Min(n)]`      | `minLength: n`      | `minimum: n`    | `minItems: n`  |
| `#[Max(n)]`      | `maxLength: n`      | `maximum: n`    | `maxItems: n`  |
| `#[Between(a,b)]`| `minLength` + `maxLength` | `minimum` + `maximum` | `minItems` + `maxItems` |
| `#[Size(n)]`     | `minLength` = `maxLength` = n | `minimum` = `maximum` = n | `minItems` = `maxItems` = n |

### Formats

| Attribute     | OpenAPI                            |
|---------------|------------------------------------|
| `#[Email]`    | `format: email`                    |
| `#[Url]`      | `format: uri`                      |
| `#[Uuid]`     | `format: uuid`                     |
| `#[Ulid]`     | `format: ulid`                     |
| `#[IP]`       | `format: ip`                       |
| `#[IPv4]`     | `format: ipv4`                     |
| `#[IPv6]`     | `format: ipv6`                     |
| `#[Date]`     | `format: date`                     |
| `#[Json]`     | `contentMediaType: application/json` |
| `#[Image]`    | `format: binary`                   |
| `#[File]`     | `format: binary`                   |

### Patterns

| Attribute              | OpenAPI `pattern`                |
|------------------------|----------------------------------|
| `#[Regex('/pat/')]`    | regex value                      |
| `#[Alpha]`             | `^[a-zA-Z]+$`                    |
| `#[AlphaDash]`         | `^[a-zA-Z0-9_-]+$`              |
| `#[AlphaNumeric]`      | `^[a-zA-Z0-9]+$`                |
| `#[Lowercase]`         | `^[a-z]+$`                       |
| `#[Uppercase]`         | `^[A-Z]+$`                       |
| `#[Digits(n)]`         | `^\d{n}$`                        |
| `#[DigitsBetween(a,b)]`| `^\d{a,b}$`                      |
| `#[StartsWith('a','b')]` | `^(a\|b)`                      |
| `#[EndsWith('a','b')]`   | `(a\|b)$`                      |

### Other

| Attribute          | Effect                                       |
|--------------------|----------------------------------------------|
| `#[Nullable]`      | `nullable: true`                             |
| `#[MultipleOf(n)]` | extension `x-multipleOf: n` (for number)     |

Attributes without a direct OpenAPI equivalent (`Exists`, `Unique`, `Same`, `Different`, `Confirmed`, etc.) are silently skipped.

## Property renaming

The package fully supports spatie/laravel-data's name mapping mechanism.

### Class level

```php
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class UserProfileData extends Data
{
    public function __construct(
        public string $firstName,   // -> first_name
        public string $lastName,    // -> last_name
        public int $userAge,        // -> user_age
    ) {
    }
}
```

### Property level

```php
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\MapInputName;

class OrderData extends Data
{
    public function __construct(
        #[MapInputName('order_id')]
        public string $orderId,

        #[MapOutputName('total_amount')]
        public float $totalAmount,
    ) {
    }
}
```

### Supported mappers

- `SnakeCaseMapper` -- `camelCase` -> `snake_case`
- `CamelCaseMapper` -- `snake_case` -> `camelCase`
- `#[MapName('custom_name')]` -- combined input + output mapping
- `#[MapInputName('name')]` -- input-only mapping
- `#[MapOutputName('name')]` -- output-only mapping
- Any custom mapper implementing the laravel-data contract

## Special properties handling

### Nullable

```php
class Data extends \Spatie\LaravelData\Data
{
    public function __construct(
        public ?string $name,  // nullable: true, not in required
    ) {
    }
}
```

### Default values

```php
class Data extends \Spatie\LaravelData\Data
{
    public function __construct(
        public string $status = 'active',  // not in required
    ) {
    }
}
```

### Hidden

```php
use Spatie\LaravelData\Attributes\Hidden;

class Data extends \Spatie\LaravelData\Data
{
    public function __construct(
        public string $name,

        #[Hidden]
        public string $internalToken,  // fully excluded from schema
    ) {
    }
}
```

Behavior is configurable via `skip_hidden` in configuration.

### Computed

```php
use Spatie\LaravelData\Attributes\Computed;

class UserData extends \Spatie\LaravelData\Data
{
    #[Computed]
    public string $fullName;

    public function __construct(
        public string $firstName,
        public string $lastName,
    ) {
        $this->fullName = "$firstName $lastName";
    }
}
```

The `fullName` property will get `readOnly: true` in the OpenAPI schema. Behavior is configurable via `computed_as_readonly`.

### Lazy

```php
use Spatie\LaravelData\Lazy;

class UserData extends \Spatie\LaravelData\Data
{
    public function __construct(
        public string $name,
        public Lazy|AddressData $address,  // not in required
    ) {
    }
}
```

Lazy properties are treated as optional by default. Behavior is configurable via `lazy_as_optional`.

## OpenApiSchema interface

For cases where automatic schema generation is not suitable, you can define the schema manually via the `OpenApiSchema` interface.

### Manual schema definition

```php
use Skiexx\LaravelDataScramble\Contracts\OpenApiSchema;

class ExternalPaymentResponse implements OpenApiSchema
{
    public function __construct(
        public string $transactionId,
        public float $amount,
        public string $currency,
    ) {
    }

    public static function openApiSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'transaction_id' => [
                    'type' => 'string',
                    'format' => 'uuid',
                    'description' => 'Unique transaction identifier',
                ],
                'amount' => [
                    'type' => 'number',
                    'format' => 'double',
                    'description' => 'Payment amount',
                ],
                'currency' => [
                    'type' => 'string',
                    'enum' => ['USD', 'EUR', 'RUB'],
                ],
            ],
            'required' => ['transaction_id', 'amount', 'currency'],
        ];
    }
}
```

This interface can be applied to **any** class -- it doesn't need to extend `Data`. The Scramble extension recognizes it automatically.

### Auto-generation via HasOpenApiSchema

If the class extends `Data` and you want to implement `OpenApiSchema` without manually describing all properties, use the `HasOpenApiSchema` trait:

```php
use Spatie\LaravelData\Data;
use Skiexx\LaravelDataScramble\Contracts\OpenApiSchema;
use Skiexx\LaravelDataScramble\Traits\HasOpenApiSchema;

class UserData extends Data implements OpenApiSchema
{
    use HasOpenApiSchema;

    public function __construct(
        public string $name,
        public string $email,
    ) {
    }
}
```

The trait automatically generates a schema array based on class properties. This is useful when you want other parts of your application to call `UserData::openApiSchema()` to obtain the schema programmatically.

## Format traits

The package provides a set of traits that can be used on classes implementing `OpenApiSchema` for standard types:

| Trait            | OpenAPI `type`   | OpenAPI `format`  |
|------------------|------------------|-------------------|
| `StringFormat`   | `string`         | --                |
| `IntegerFormat`  | `integer`        | --                |
| `NumberFormat`   | `number`         | --                |
| `BooleanFormat`  | `boolean`        | --                |
| `ArrayFormat`    | `array`          | --                |
| `DateFormat`     | `string`         | `date-time`       |
| `UuidFormat`     | `string`         | `uuid`            |
| `EmailFormat`    | `string`         | `email`           |

### Usage example

```php
use Skiexx\LaravelDataScramble\Contracts\OpenApiSchema;
use Skiexx\LaravelDataScramble\Traits\Formats\UuidFormat;

class UserId implements OpenApiSchema
{
    use UuidFormat;

    public function __construct(
        public readonly string $value,
    ) {
    }

    public static function openApiSchema(): array
    {
        return static::openApiType();
    }
}
```

Result:

```yaml
UserId:
  type: string
  format: uuid
```

## Data classes as controller input parameters

The package automatically recognizes Data classes used as controller method parameters and correctly generates OpenAPI parameters:

- **GET/DELETE/HEAD** -- Data class properties are displayed as **query parameters**
- **POST/PUT/PATCH** -- Data class properties are displayed as **request body** (`application/json`)
- **`#[FromRouteParameter]`** -- properties are displayed as **path parameters**

### Example

```php
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;

class GetUsersData extends Data
{
    public function __construct(
        #[Min(1), Max(100)]
        public int $page,

        public ?string $search,

        public int $perPage = 15,
    ) {
    }
}

class UserController
{
    // GET /api/users?page=1&search=john&perPage=15
    public function index(GetUsersData $data): JsonResource
    {
        return JsonResource::collection(User::paginate($data->perPage));
    }
}
```

Result -- query parameters:

```yaml
parameters:
  - name: page
    in: query
    required: true
    schema:
      type: integer
      minimum: 1
      maximum: 100
  - name: search
    in: query
    required: false
    schema:
      type:
        - string
        - "null"
  - name: perPage
    in: query
    required: false
    schema:
      type: integer
```

### For POST requests

```php
class CreateUserData extends Data
{
    public function __construct(
        #[Min(2), Max(255)]
        public string $name,

        #[Email]
        public string $email,
    ) {
    }
}

class UserController
{
    // POST /api/users -- body: { "name": "...", "email": "..." }
    public function store(CreateUserData $data): JsonResource
    {
        return new JsonResource(User::create($data->toArray()));
    }
}
```

Result -- request body (`application/json`).

### `#[FromRouteParameter]` support

Use the built-in laravel-data attribute for properties taken from the route:

```php
use Spatie\LaravelData\Attributes\FromRouteParameter;

class UpdateUserData extends Data
{
    public function __construct(
        #[FromRouteParameter('user')]
        public int $userId,

        #[Min(2), Max(255)]
        public string $name,

        #[Email]
        public string $email,
    ) {
    }
}

class UserController
{
    // PUT /api/users/{user}
    public function update(UpdateUserData $data): JsonResource
    {
        return new JsonResource(User::findOrFail($data->userId)->update($data->toArray()));
    }
}
```

Result:

```yaml
parameters:
  - name: user
    in: path
    required: true
    schema:
      type: integer
requestBody:
  content:
    application/json:
      schema:
        type: object
        required: [name, email]
        properties:
          name:
            type: string
            minLength: 2
            maxLength: 255
          email:
            type: string
            format: email
```

The `userId` property doesn't end up in the body -- it's displayed as a path parameter named `user` (from the `#[FromRouteParameter('user')]` attribute).

### `#[FromRouteParameterProperty]` support

For getting a model property from a route parameter:

```php
use Spatie\LaravelData\Attributes\FromRouteParameterProperty;

class OrderData extends Data
{
    public function __construct(
        #[FromRouteParameterProperty('user', property: 'id')]
        public int $userId,

        public string $product,
    ) {
    }
}
```

Properties with `#[FromRouteParameterProperty]` are also excluded from query/body and don't appear in documentation as request parameters.

### Input parameter name mapping

When using `#[MapInputName]` or `#[MapName]`, parameter names in OpenAPI will match the mapping:

```php
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class FilterData extends Data
{
    public function __construct(
        public string $firstName,   // query param: first_name
        public string $lastName,    // query param: last_name
    ) {
    }
}
```

---

## ResponseData attribute

When a controller returns an anonymous `JsonResource` to wrap the response in `{ "data": ... }`, Scramble cannot determine the actual data type. The `#[ResponseData]` attribute solves this problem.

### The problem

```php
class UserController
{
    // Scramble sees JsonResource but doesn't know it contains UserData
    public function show(string $id): JsonResource
    {
        return new JsonResource($action->execute($id));
    }

    public function index(): JsonResource
    {
        return JsonResource::collection($action->execute());
    }
}
```

### The solution

```php
use Skiexx\LaravelDataScramble\Attributes\ResponseData;

class UserController
{
    #[ResponseData(UserData::class)]
    public function show(string $id): JsonResource
    {
        return new JsonResource($action->execute($id));
    }

    #[ResponseData(UserData::class, collection: true)]
    public function index(): JsonResource
    {
        return JsonResource::collection($action->execute());
    }
}
```

### Attribute parameters

| Parameter         | Type   | Default        | Description                                        |
|-------------------|--------|----------------|----------------------------------------------------|
| `dataClass`       | string | (required)     | FQCN of the Data class                             |
| `collection`      | bool   | `false`        | Array of objects `{ "data": [{...}] }`             |
| `paginated`       | bool   | `false`        | LengthAwarePaginator with meta + links             |
| `cursorPaginated` | bool   | `false`        | CursorPaginator with meta                          |
| `status`          | int    | `200`          | HTTP status code (e.g. 201 for POST Create)        |
| `wrapped`         | bool   | `true`         | `{ "data": ... }` wrapper, always true for paginated |

### Usage examples

#### Single object

```php
#[ResponseData(UserData::class)]
public function show(string $id): JsonResource
{
    return new JsonResource($action->execute($id));
}
```

#### Collection

```php
#[ResponseData(UserData::class, collection: true)]
public function index(): JsonResource
{
    return JsonResource::collection($action->execute());
}
```

#### Pagination (LengthAwarePaginator)

```php
#[ResponseData(UserData::class, paginated: true)]
public function index(): JsonResource
{
    return JsonResource::collection($action->execute());
}
```

Generates the complete Laravel pagination structure with `data`, `links` (first, last, prev, next), and `meta` (current_page, from, last_page, links, path, per_page, to, total).

#### Cursor pagination

```php
#[ResponseData(UserData::class, cursorPaginated: true)]
public function index(): JsonResource
{
    return JsonResource::collection($action->execute());
}
```

Generates `data` + `meta` (path, per_page, next_cursor, prev_cursor, next_page_url, prev_page_url).

#### Custom status code

```php
#[ResponseData(UserData::class, status: 201)]
public function store(CreateUserData $data): JsonResource
{
    return new JsonResource($action->execute($data));
}
```

#### Without wrapper

```php
#[ResponseData(UserData::class, wrapped: false)]
public function show(string $id): JsonResource
{
    return new JsonResource($action->execute($id));
}
```

> **Note:** for `paginated` and `cursorPaginated`, the `wrapped` parameter is ignored -- the wrapper with `data`, `meta`, and `links` is always applied, as this is the standard Laravel paginator response format.

## Advanced configuration

### Manual extension registration

If you need to control the registration moment or register the extension only for a specific API:

```php
// config/laravel-data-scramble.php
return [
    'auto_register' => false,
];
```

```php
// AppServiceProvider.php
use Dedoc\Scramble\Scramble;
use Skiexx\LaravelDataScramble\Extensions\LaravelDataTypeToSchemaExtension;

public function boot(): void
{
    Scramble::registerExtension(LaravelDataTypeToSchemaExtension::class);
}
```

### Overriding resolvers

You can create your own extension inheriting from `LaravelDataTypeToSchemaExtension`:

```php
use Skiexx\LaravelDataScramble\Extensions\LaravelDataTypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\ObjectType;

class CustomDataExtension extends LaravelDataTypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        if ($type instanceof ObjectType && str_starts_with($type->name, 'App\\Data\\')) {
            return parent::shouldHandle($type);
        }

        return false;
    }

    public function toSchema(Type $type): OpenApiType
    {
        /** @var ObjectType $type */
        $schema = parent::toSchema($type);
        $schema->setDescription("Auto-generated schema for {$type->name}");

        return $schema;
    }
}
```

Register your extension:

```php
// config/laravel-data-scramble.php
return [
    'auto_register' => false,
];
```

```php
// AppServiceProvider.php
Scramble::registerExtension(CustomDataExtension::class);
```

### Overriding ValidationAttributeMap

To add support for custom validation attributes or change existing mapping:

```php
use Skiexx\LaravelDataScramble\Support\ValidationAttributeMap;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Spatie\LaravelData\Attributes\Validation\ValidationAttribute;

class ExtendedValidationMap extends ValidationAttributeMap
{
    public static function apply(ValidationAttribute $attribute, OpenApiType $type): void
    {
        parent::apply($attribute, $type);

        if ($attribute instanceof YourCustomAttribute) {
            $type->format('your-custom-format');
        }
    }
}
```

### Custom DataClassSchemaResolver

For full control over the schema generation process:

```php
use Skiexx\LaravelDataScramble\Resolvers\DataClassSchemaResolver;

class CustomSchemaResolver extends DataClassSchemaResolver
{
    // Override methods as needed
}
```

And use it in your extension:

```php
class CustomDataExtension extends TypeToSchemaExtension
{
    public function toSchema(Type $type): OpenApiType
    {
        $resolver = new CustomSchemaResolver($this->components);
        return $resolver->resolve($type->name);
    }
}
```

### Using with multiple Scramble APIs

If you use multiple API documentation sets in Scramble:

```php
use Dedoc\Scramble\Scramble;
use Skiexx\LaravelDataScramble\Extensions\LaravelDataTypeToSchemaExtension;

// Extension is registered globally for all APIs
Scramble::registerExtension(LaravelDataTypeToSchemaExtension::class);

// Configure a specific API
Scramble::configure('v2')
    ->routes(fn (Route $route) => str_starts_with($route->uri, 'api/v2'));
```

## Development

### Docker environment

The project includes Docker configuration for development without local PHP installation:

```bash
# Build container
podman compose build

# Install dependencies
podman compose run --rm app composer install

# Run tests
podman compose run --rm app vendor/bin/pest

# Code style check
podman compose run --rm app vendor/bin/pint

# Static analysis
podman compose run --rm app vendor/bin/phpstan analyse --memory-limit=512M

# Interactive shell
podman compose run --rm app bash
```

### Code standards

- PSR-12 via Laravel Pint
- `declare(strict_types=1)` in all PHP files
- PHPStan level 5
- Tests with Pest

## Architecture

```
src/
├── LaravelDataScrambleServiceProvider.php  -- Service provider, registers extensions
├── Attributes/
│   └── ResponseData.php                    -- Attribute for describing JsonResource responses
├── Extensions/
│   ├── LaravelDataTypeToSchemaExtension.php -- TypeToSchemaExtension for Data classes
│   └── ResponseDataOperationExtension.php  -- OperationExtension for #[ResponseData]
├── Extractors/
│   └── DataParametersExtractor.php         -- ParameterExtractor for Data classes
├── Contracts/
│   └── OpenApiSchema.php                   -- Interface for manual schema definition
├── Resolvers/
│   ├── DataClassSchemaResolver.php         -- Orchestrator: builds ObjectType from properties
│   ├── PropertyTypeResolver.php            -- PHP type -> OpenAPI type
│   ├── ValidationConstraintResolver.php    -- Validation attributes -> OpenAPI constraints
│   └── NameMappingResolver.php             -- Name mapping (input/output)
├── Traits/
│   ├── HasOpenApiSchema.php                -- Default OpenApiSchema implementation
│   └── Formats/                            -- Format traits (String, Uuid, Email...)
└── Support/
    └── ValidationAttributeMap.php          -- Validation attribute mapping registry
```

### How it works

1. **Scramble** calls `shouldHandle()` on each extension for every type found in controllers during documentation generation.

2. **`LaravelDataTypeToSchemaExtension`** checks if the type is a `BaseData` descendant (Data, Resource, Dto) or implements `OpenApiSchema`.

3. **`DataClassSchemaResolver`** gets class metadata via `DataConfig::getDataClass()` and for each property:
   - Checks if it's hidden (`#[Hidden]`)
   - Determines the name via `NameMappingResolver` (considering `#[MapOutputName]`)
   - Determines the type via `PropertyTypeResolver` (scalars, dates, enums, nested Data)
   - Applies constraints via `ValidationConstraintResolver` (Min, Max, Email, etc.)
   - Handles nullable, lazy, computed, default

4. **`reference()`** creates a `$ref` link via `ClassBasedReference` so nested objects are not duplicated inline but placed in `#/components/schemas/`.

5. **`ResponseDataOperationExtension`** works at the operation level (after `ResponseExtension`): reads the `#[ResponseData]` attribute from the controller method and replaces the response schema that Scramble generated for the anonymous `JsonResource` with the correct schema containing the specified Data class, wrapper, and pagination.

## License

MIT
