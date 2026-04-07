<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Auto Register Extension
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically register its Scramble
    | extension. Set to false if you want to register it manually.
    |
    */
    'auto_register' => true,

    /*
    |--------------------------------------------------------------------------
    | Skip Hidden Properties
    |--------------------------------------------------------------------------
    |
    | When enabled, properties marked with #[Hidden] will be excluded
    | from the generated OpenAPI schema.
    |
    */
    'skip_hidden' => true,

    /*
    |--------------------------------------------------------------------------
    | Lazy Properties as Optional
    |--------------------------------------------------------------------------
    |
    | When enabled, Lazy properties will be treated as optional
    | and not included in the required array.
    |
    */
    'lazy_as_optional' => true,

    /*
    |--------------------------------------------------------------------------
    | Computed Properties as ReadOnly
    |--------------------------------------------------------------------------
    |
    | When enabled, properties marked with #[Computed] will have
    | readOnly set to true in the OpenAPI schema.
    |
    */
    'computed_as_readonly' => true,

    /*
    |--------------------------------------------------------------------------
    | Strip Route Prefix
    |--------------------------------------------------------------------------
    |
    | Если задан — этот префикс будет удалён из начала пути каждого
    | маршрута при генерации OpenAPI. Например, если роуты имеют
    | префикс 'web/' (web/users, web/orders), установите 'web'
    | и в OpenAPI пути будут /users, /orders.
    |
    | Установите null чтобы не обрезать ничего.
    |
    */
    'strip_prefix' => null,
];
