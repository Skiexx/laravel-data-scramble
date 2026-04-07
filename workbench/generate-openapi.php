<?php

/**
 * Генерирует OpenAPI JSON из тестовых Data-классов и контроллера.
 *
 * Использование: php workbench/generate-openapi.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Support\Facades\Route;
use Skiexx\LaravelDataScramble\LaravelDataScrambleServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;

$testCase = new class ('generate') extends \Orchestra\Testbench\TestCase {
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            ScrambleServiceProvider::class,
            LaravelDataScrambleServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        // Включаем strip_prefix для теста
        config()->set('skiexx-data-scramble.strip_prefix', 'web');
    }

    public function generate(): void
    {
        $this->setUp();

        // web/ — публичные роуты (должны попасть в OpenAPI без префикса web/)
        Route::prefix('web')->group(function (): void {
            Route::get('users', [\Workbench\App\Http\Controllers\UserController::class, 'index']);
            Route::get('users/{id}', [\Workbench\App\Http\Controllers\UserController::class, 'show']);
            Route::post('users', [\Workbench\App\Http\Controllers\UserController::class, 'store']);
            Route::put('users/{user}', [\Workbench\App\Http\Controllers\UserController::class, 'update']);
            Route::delete('users/{id}', [\Workbench\App\Http\Controllers\UserController::class, 'destroy']);

            Route::get('products', fn (\Workbench\App\Data\ProductData $filter): \Workbench\App\Data\ProductData => $filter);
            Route::post('products', fn (\Workbench\App\Data\ProductData $data): \Workbench\App\Data\ProductData => $data);
        });

        // internal/ — НЕ должны попасть в OpenAPI
        Route::prefix('internal')->group(function (): void {
            Route::get('health', fn (): array => ['status' => 'ok']);
            Route::get('metrics', fn (): array => ['uptime' => 123]);
        });

        // Генерируем OpenAPI — фильтруем только web/
        $config = Scramble::getGeneratorConfig('default');
        $config->routes(fn (\Illuminate\Routing\Route $route) => str_starts_with($route->uri, 'web/'));

        $generator = app(Generator::class);
        $doc = $generator($config);

        echo json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
};

$testCase->generate();
