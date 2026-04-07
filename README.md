# Laravel Data Scramble

> **[English version](README.en.md)**

Бесплатное расширение для [dedoc/scramble](https://github.com/dedoc/scramble), которое автоматически генерирует OpenAPI-схемы из классов [spatie/laravel-data](https://github.com/spatie/laravel-data).

Пакет анализирует ваши `Data`, `Resource` и `Dto` классы, используемые как входные параметры контроллеров (вместо `FormRequest`) и как возвращаемые типы (вместо `JsonResource`), и строит полноценную OpenAPI-документацию для каждого маршрута.

## Возможности

- Автоматический парсинг `Data`, `Resource`, `Dto` классов
- Поддержка всех скалярных типов PHP: `string`, `int`, `float`, `bool`, `array`
- Поддержка `Carbon`, `DateTime`, `DateTimeImmutable`, `DateInterval`
- Поддержка backed enum (`string` и `int`)
- Вложенные Data-объекты с автоматическим созданием `$ref`-ссылок в `#/components/schemas/`
- Коллекции Data-объектов (`Data[]`, `#[DataCollectionOf]`)
- Маппинг 20+ атрибутов валидации spatie/laravel-data в OpenAPI-ограничения
- Поддержка переименования свойств через `#[MapOutputName]`, `#[MapInputName]`, `#[MapName]`
- Поддержка `SnakeCaseMapper`, `CamelCaseMapper` и пользовательских маперов
- Обработка `nullable`, `optional`, `default`, `computed`, `hidden`, `lazy` свойств
- Интерфейс `OpenApiSchema` для полностью ручного определения схемы любого класса
- Трейт `HasOpenApiSchema` для автогенерации схемы из Data-класса
- Набор трейтов-форматов (`StringFormat`, `UuidFormat`, `EmailFormat` и др.)
- Атрибут `#[ResponseData]` для описания ответов анонимных `JsonResource`
- Поддержка пагинации (LengthAwarePaginator, CursorPaginator) с meta/links

## Требования

- PHP ^8.4
- Laravel ^12.0
- spatie/laravel-data ^4.0
- dedoc/scramble ^0.13

## Установка

```bash
composer require skiexx/laravel-data-scramble
```

Сервис-провайдер регистрируется автоматически через Laravel auto-discovery. Расширение Scramble также регистрируется автоматически при загрузке пакета.

### Публикация конфигурации (опционально)

```bash
php artisan vendor:publish --tag="laravel-data-scramble-config"
```

Файл конфигурации будет создан в `config/laravel-data-scramble.php`.

## Быстрый старт

После установки пакет начинает работать сразу. Никаких дополнительных настроек не требуется.

### Пример контроллера

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

### Пример Data-класса

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

Этот класс автоматически сгенерирует OpenAPI-схему:

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

## Конфигурация

```php
// config/laravel-data-scramble.php

return [
    // Автоматическая регистрация расширения в Scramble.
    // Установите false, если хотите зарегистрировать вручную.
    'auto_register' => true,

    // Пропускать свойства с атрибутом #[Hidden].
    'skip_hidden' => true,

    // Lazy-свойства считаются необязательными (не попадают в required).
    'lazy_as_optional' => true,

    // Computed-свойства получают readOnly: true.
    'computed_as_readonly' => true,
];
```

## Поддерживаемые типы

### Скалярные типы

| PHP-тип   | OpenAPI-тип             |
|-----------|-------------------------|
| `string`  | `type: string`          |
| `int`     | `type: integer`         |
| `float`   | `type: number`          |
| `bool`    | `type: boolean`         |
| `array`   | `type: array`           |
| `mixed`   | без ограничений         |

### Даты

| PHP-тип                    | OpenAPI                              |
|----------------------------|--------------------------------------|
| `Carbon\Carbon`            | `type: string, format: date-time`    |
| `Carbon\CarbonImmutable`   | `type: string, format: date-time`    |
| `DateTime`                 | `type: string, format: date-time`    |
| `DateTimeImmutable`        | `type: string, format: date-time`    |
| `DateTimeInterface`        | `type: string, format: date-time`    |
| `DateInterval`             | `type: string, format: duration`     |

### Перечисления (Enum)

Backed enum автоматически преобразуется в соответствующий тип с перечислением допустимых значений:

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

Результат:

```yaml
status:
  type: string
  enum:
    - active
    - inactive
```

Для `int`-backed enum будет использован `type: integer`.

### Вложенные Data-объекты

Вложенные объекты автоматически создают `$ref`-ссылки в `#/components/schemas/`:

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

Результат:

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

### Коллекции Data-объектов

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

Результат:

```yaml
members:
  type: array
  items:
    $ref: '#/components/schemas/UserData'
```

## Маппинг атрибутов валидации

Пакет автоматически преобразует атрибуты валидации spatie/laravel-data в соответствующие ограничения OpenAPI:

### Ограничения размера

| Атрибут          | string              | integer/number  | array          |
|------------------|---------------------|-----------------|----------------|
| `#[Min(n)]`      | `minLength: n`      | `minimum: n`    | `minItems: n`  |
| `#[Max(n)]`      | `maxLength: n`      | `maximum: n`    | `maxItems: n`  |
| `#[Between(a,b)]`| `minLength` + `maxLength` | `minimum` + `maximum` | `minItems` + `maxItems` |
| `#[Size(n)]`     | `minLength` = `maxLength` = n | `minimum` = `maximum` = n | `minItems` = `maxItems` = n |

### Форматы

| Атрибут       | OpenAPI                            |
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

### Паттерны

| Атрибут              | OpenAPI `pattern`                |
|----------------------|----------------------------------|
| `#[Regex('/pat/')]`  | значение регулярного выражения   |
| `#[Alpha]`           | `^[a-zA-Z]+$`                    |
| `#[AlphaDash]`       | `^[a-zA-Z0-9_-]+$`              |
| `#[AlphaNumeric]`    | `^[a-zA-Z0-9]+$`                |
| `#[Lowercase]`       | `^[a-z]+$`                       |
| `#[Uppercase]`       | `^[A-Z]+$`                       |
| `#[Digits(n)]`       | `^\d{n}$`                        |
| `#[DigitsBetween(a,b)]` | `^\d{a,b}$`                   |
| `#[StartsWith('a','b')]` | `^(a\|b)`                    |
| `#[EndsWith('a','b')]`   | `(a\|b)$`                    |

### Прочие

| Атрибут          | Эффект                                       |
|------------------|----------------------------------------------|
| `#[Nullable]`    | `nullable: true`                             |
| `#[MultipleOf(n)]` | расширение `x-multipleOf: n` (для number) |

Атрибуты без прямого аналога в OpenAPI (`Exists`, `Unique`, `Same`, `Different`, `Confirmed` и др.) молча пропускаются.

## Переименование свойств

Пакет полностью поддерживает механизм маппинга имен spatie/laravel-data.

### На уровне класса

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

### На уровне свойства

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

### Поддерживаемые маперы

- `SnakeCaseMapper` -- `camelCase` -> `snake_case`
- `CamelCaseMapper` -- `snake_case` -> `camelCase`
- `#[MapName('custom_name')]` -- комбинированный маппинг input + output
- `#[MapInputName('name')]` -- маппинг только для входных данных
- `#[MapOutputName('name')]` -- маппинг только для выходных данных
- Любой пользовательский мапер, реализующий контракт laravel-data

## Обработка специальных свойств

### Nullable

```php
class Data extends \Spatie\LaravelData\Data
{
    public function __construct(
        public ?string $name,  // nullable: true, не в required
    ) {
    }
}
```

### Default-значения

```php
class Data extends \Spatie\LaravelData\Data
{
    public function __construct(
        public string $status = 'active',  // не в required
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
        public string $internalToken,  // полностью исключено из схемы
    ) {
    }
}
```

Поведение настраивается через `skip_hidden` в конфигурации.

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

Свойство `fullName` получит `readOnly: true` в OpenAPI-схеме. Поведение настраивается через `computed_as_readonly`.

### Lazy

```php
use Spatie\LaravelData\Lazy;

class UserData extends \Spatie\LaravelData\Data
{
    public function __construct(
        public string $name,
        public Lazy|AddressData $address,  // не в required
    ) {
    }
}
```

Lazy-свойства по умолчанию считаются необязательными. Поведение настраивается через `lazy_as_optional`.

## Интерфейс OpenApiSchema

Для случаев, когда автоматическая генерация схемы не подходит, вы можете определить схему вручную через интерфейс `OpenApiSchema`.

### Ручное определение схемы

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
                    'description' => 'Уникальный идентификатор транзакции',
                ],
                'amount' => [
                    'type' => 'number',
                    'format' => 'double',
                    'description' => 'Сумма платежа',
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

Этот интерфейс можно повесить на **любой** класс -- он не обязан наследовать `Data`. Расширение Scramble распознает его автоматически.

### Автогенерация через HasOpenApiSchema

Если класс наследует `Data` и вы хотите реализовать `OpenApiSchema` без ручного описания всех свойств, используйте трейт `HasOpenApiSchema`:

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

Трейт автоматически генерирует массив схемы на основе свойств класса. Это полезно, когда вы хотите, чтобы другие части вашего приложения могли вызвать `UserData::openApiSchema()` для получения схемы программно.

## Трейты форматов

Пакет предоставляет набор трейтов, которые можно использовать на классах, реализующих `OpenApiSchema`, для стандартных типов:

| Трейт            | OpenAPI `type`   | OpenAPI `format`  |
|------------------|------------------|-------------------|
| `StringFormat`   | `string`         | --                |
| `IntegerFormat`  | `integer`        | --                |
| `NumberFormat`   | `number`         | --                |
| `BooleanFormat`  | `boolean`        | --                |
| `ArrayFormat`    | `array`          | --                |
| `DateFormat`     | `string`         | `date-time`       |
| `UuidFormat`     | `string`         | `uuid`            |
| `EmailFormat`    | `string`         | `email`           |

### Пример использования

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

Результат:

```yaml
UserId:
  type: string
  format: uuid
```

## Data-классы как входные параметры контроллера

Пакет автоматически распознает Data-классы, используемые как параметры методов контроллера, и правильно генерирует OpenAPI-параметры:

- **GET/DELETE/HEAD** — свойства Data-класса отображаются как **query parameters**
- **POST/PUT/PATCH** — свойства Data-класса отображаются как **request body** (`application/json`)
- **`#[FromRouteParameter]`** — свойства отображаются как **path parameters**

### Пример

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

Результат — query parameters:

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

### Для POST-запросов

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
    // POST /api/users — body: { "name": "...", "email": "..." }
    public function store(CreateUserData $data): JsonResource
    {
        return new JsonResource(User::create($data->toArray()));
    }
}
```

Результат — request body (`application/json`).

### Поддержка `#[FromRouteParameter]`

Используйте встроенный атрибут laravel-data для свойств, которые берутся из маршрута:

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
        // $data->userId автоматически берется из route param {user}
        return new JsonResource(User::findOrFail($data->userId)->update($data->toArray()));
    }
}
```

Результат:

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

Свойство `userId` не попадает в body — оно отображается как path parameter с именем `user` (из атрибута `#[FromRouteParameter('user')]`).

### Поддержка `#[FromRouteParameterProperty]`

Для получения свойства модели из route parameter:

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

Свойства с `#[FromRouteParameterProperty]` также исключаются из query/body и не попадают в документацию как параметры запроса.

### Маппинг имен для входных параметров

При использовании `#[MapInputName]` или `#[MapName]`, имена параметров в OpenAPI будут соответствовать маппингу:

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

## Атрибут ResponseData

Когда контроллер возвращает анонимный `JsonResource` для обертки ответа в `{ "data": ... }`, Scramble не может определить реальный тип данных. Атрибут `#[ResponseData]` решает эту проблему.

### Проблема

```php
class UserController
{
    // Scramble видит JsonResource, но не знает что внутри UserData
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

### Решение

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

### Параметры атрибута

| Параметр          | Тип    | По умолчанию | Описание                                           |
|-------------------|--------|--------------|-----------------------------------------------------|
| `dataClass`       | string | (обязательный) | FQCN Data-класса                                  |
| `collection`      | bool   | `false`      | Массив объектов `{ "data": [{...}] }`              |
| `paginated`       | bool   | `false`      | LengthAwarePaginator с meta + links                |
| `cursorPaginated` | bool   | `false`      | CursorPaginator с meta                             |
| `status`          | int    | `200`        | HTTP status code (например 201 для POST Create)    |
| `wrapped`         | bool   | `true`       | Обертка `{ "data": ... }`, для paginated всегда true |

### Примеры использования

#### Одиночный объект

```php
#[ResponseData(UserData::class)]
public function show(string $id): JsonResource
{
    return new JsonResource($action->execute($id));
}
```

Результат:

```yaml
responses:
  200:
    content:
      application/json:
        schema:
          type: object
          required: [data]
          properties:
            data:
              $ref: '#/components/schemas/UserData'
```

#### Коллекция

```php
#[ResponseData(UserData::class, collection: true)]
public function index(): JsonResource
{
    return JsonResource::collection($action->execute());
}
```

Результат:

```yaml
responses:
  200:
    content:
      application/json:
        schema:
          type: object
          required: [data]
          properties:
            data:
              type: array
              items:
                $ref: '#/components/schemas/UserData'
```

#### Пагинация (LengthAwarePaginator)

```php
#[ResponseData(UserData::class, paginated: true)]
public function index(): JsonResource
{
    return JsonResource::collection($action->execute());
}
```

Результат включает полную структуру пагинации Laravel:

```yaml
responses:
  200:
    content:
      application/json:
        schema:
          type: object
          required: [data, links, meta]
          properties:
            data:
              type: array
              items:
                $ref: '#/components/schemas/UserData'
            links:
              type: object
              properties:
                first: { type: [string, "null"] }
                last: { type: [string, "null"] }
                prev: { type: [string, "null"] }
                next: { type: [string, "null"] }
            meta:
              type: object
              properties:
                current_page: { type: integer }
                from: { type: [integer, "null"] }
                last_page: { type: integer }
                links:
                  type: array
                  items:
                    type: object
                    properties:
                      url: { type: [string, "null"] }
                      label: { type: string }
                      active: { type: boolean }
                path: { type: string }
                per_page: { type: integer }
                to: { type: [integer, "null"] }
                total: { type: integer }
```

#### Cursor-пагинация

```php
#[ResponseData(UserData::class, cursorPaginated: true)]
public function index(): JsonResource
{
    return JsonResource::collection($action->execute());
}
```

Результат:

```yaml
responses:
  200:
    content:
      application/json:
        schema:
          type: object
          required: [data, meta]
          properties:
            data:
              type: array
              items:
                $ref: '#/components/schemas/UserData'
            meta:
              type: object
              properties:
                path: { type: string }
                per_page: { type: integer }
                next_cursor: { type: [string, "null"] }
                prev_cursor: { type: [string, "null"] }
                next_page_url: { type: [string, "null"] }
                prev_page_url: { type: [string, "null"] }
```

#### Кастомный status code

```php
#[ResponseData(UserData::class, status: 201)]
public function store(CreateUserData $data): JsonResource
{
    return new JsonResource($action->execute($data));
}
```

#### Без обертки

Если ответ не обернут в `{ "data": ... }`:

```php
#[ResponseData(UserData::class, wrapped: false)]
public function show(string $id): JsonResource
{
    return new JsonResource($action->execute($id));
}
```

Результат -- прямая ссылка на схему без обертки:

```yaml
responses:
  200:
    content:
      application/json:
        schema:
          $ref: '#/components/schemas/UserData'
```

Для коллекции без обертки:

```php
#[ResponseData(UserData::class, collection: true, wrapped: false)]
```

Результат:

```yaml
responses:
  200:
    content:
      application/json:
        schema:
          type: array
          items:
            $ref: '#/components/schemas/UserData'
```

> **Примечание:** для `paginated` и `cursorPaginated` параметр `wrapped` игнорируется -- обертка с `data`, `meta` и `links` применяется всегда, так как это стандартный формат ответа Laravel-пагинатора.

## Продвинутая настройка

### Ручная регистрация расширения

Если вам нужно контролировать момент регистрации или регистрировать расширение только для определенного API:

```php
// config/laravel-data-scramble.php
return [
    'auto_register' => false,  // Отключаем авторегистрацию
];
```

```php
// AppServiceProvider.php или другой провайдер
use Dedoc\Scramble\Scramble;
use Skiexx\LaravelDataScramble\Extensions\LaravelDataTypeToSchemaExtension;

public function boot(): void
{
    // Регистрация для конкретного API
    Scramble::registerExtension(LaravelDataTypeToSchemaExtension::class);
}
```

### Подмена резолверов

Если вам нужно изменить логику генерации схемы, вы можете создать собственное расширение, наследующее `LaravelDataTypeToSchemaExtension`:

```php
use Skiexx\LaravelDataScramble\Extensions\LaravelDataTypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\ObjectType;

class CustomDataExtension extends LaravelDataTypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        // Обрабатывать только классы из определенного namespace
        if ($type instanceof ObjectType && str_starts_with($type->name, 'App\\Data\\')) {
            return parent::shouldHandle($type);
        }

        return false;
    }

    public function toSchema(Type $type): OpenApiType
    {
        /** @var ObjectType $type */
        $schema = parent::toSchema($type);

        // Добавить описание ко всем схемам
        $schema->setDescription("Автогенерированная схема для {$type->name}");

        return $schema;
    }
}
```

Зарегистрируйте своё расширение:

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

### Подмена ValidationAttributeMap

Для добавления поддержки собственных атрибутов валидации или изменения маппинга существующих:

```php
use Skiexx\LaravelDataScramble\Support\ValidationAttributeMap;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Spatie\LaravelData\Attributes\Validation\ValidationAttribute;

// В вашем расширении или сервис-провайдере
class ExtendedValidationMap extends ValidationAttributeMap
{
    public static function apply(ValidationAttribute $attribute, OpenApiType $type): void
    {
        // Сначала применяем стандартные маппинги
        parent::apply($attribute, $type);

        // Добавляем кастомную обработку
        if ($attribute instanceof YourCustomAttribute) {
            $type->format('your-custom-format');
        }
    }
}
```

### Собственный DataClassSchemaResolver

Для полного контроля над процессом генерации схемы:

```php
use Skiexx\LaravelDataScramble\Resolvers\DataClassSchemaResolver;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;

class CustomSchemaResolver extends DataClassSchemaResolver
{
    // Переопределите нужные методы
}
```

И используйте его в своем расширении:

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

### Использование с несколькими API Scramble

Если вы используете несколько API-документаций в Scramble:

```php
use Dedoc\Scramble\Scramble;
use Skiexx\LaravelDataScramble\Extensions\LaravelDataTypeToSchemaExtension;

// Расширение регистрируется глобально для всех API
Scramble::registerExtension(LaravelDataTypeToSchemaExtension::class);

// Настройка конкретного API
Scramble::configure('v2')
    ->routes(fn (Route $route) => str_starts_with($route->uri, 'api/v2'));
```

## Разработка

### Docker-окружение

Проект включает Docker-конфигурацию для разработки без локальной установки PHP:

```bash
# Сборка контейнера
podman compose build

# Установка зависимостей
podman compose run --rm app composer install

# Запуск тестов
podman compose run --rm app vendor/bin/pest

# Проверка стиля кода
podman compose run --rm app vendor/bin/pint

# Интерактивная оболочка
podman compose run --rm app bash
```

### Стандарты кода

- PSR-12 через Laravel Pint
- `declare(strict_types=1)` во всех PHP-файлах
- Тесты на Pest

## Архитектура

```
src/
├── LaravelDataScrambleServiceProvider.php  -- Сервис-провайдер, регистрирует расширения
├── Attributes/
│   └── ResponseData.php                    -- Атрибут для описания ответов JsonResource
├── Extensions/
│   ├── LaravelDataTypeToSchemaExtension.php -- TypeToSchemaExtension для Data-классов
│   └── ResponseDataOperationExtension.php  -- OperationExtension для #[ResponseData]
├── Extractors/
│   └── DataParametersExtractor.php         -- ParameterExtractor для Data-классов
├── Contracts/
│   └── OpenApiSchema.php                   -- Интерфейс для ручного определения схемы
├── Resolvers/
│   ├── DataClassSchemaResolver.php         -- Оркестратор: собирает ObjectType из свойств
│   ├── PropertyTypeResolver.php            -- PHP-тип -> OpenAPI-тип
│   ├── ValidationConstraintResolver.php    -- Атрибуты валидации -> OpenAPI-ограничения
│   └── NameMappingResolver.php             -- Маппинг имен (input/output)
├── Traits/
│   ├── HasOpenApiSchema.php                -- Реализация OpenApiSchema по умолчанию
│   └── Formats/                            -- Трейты форматов (String, Uuid, Email...)
└── Support/
    └── ValidationAttributeMap.php          -- Карта маппинга атрибутов валидации
```

### Как это работает

1. **Scramble** при генерации документации вызывает `shouldHandle()` на каждом расширении для каждого типа, найденного в контроллерах.

2. **`LaravelDataTypeToSchemaExtension`** проверяет, является ли тип наследником `BaseData` (Data, Resource, Dto) или реализует `OpenApiSchema`.

3. **`DataClassSchemaResolver`** получает метаданные класса через `DataConfig::getDataClass()` и для каждого свойства:
   - Проверяет, не скрыто ли оно (`#[Hidden]`)
   - Определяет имя через `NameMappingResolver` (с учетом `#[MapOutputName]`)
   - Определяет тип через `PropertyTypeResolver` (скаляры, даты, enum, вложенные Data)
   - Применяет ограничения через `ValidationConstraintResolver` (Min, Max, Email и др.)
   - Обрабатывает nullable, lazy, computed, default

4. **`reference()`** создает `$ref`-ссылку через `ClassBasedReference`, чтобы вложенные объекты не дублировались inline, а были вынесены в `#/components/schemas/`.

5. **`ResponseDataOperationExtension`** работает на уровне операций (после `ResponseExtension`): читает `#[ResponseData]` атрибут с метода контроллера и заменяет response schema, которую Scramble сгенерировал для анонимного `JsonResource`, на правильную схему с указанным Data-классом, оберткой и пагинацией.

## Лицензия

MIT
