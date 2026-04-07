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

// Создаём минимальное Laravel-приложение через Testbench
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
    }

    public function generate(): void
    {
        $this->setUp();

        // Регистрируем маршруты
        Route::prefix('api')->group(function (): void {
            Route::get('users', [\Workbench\App\Http\Controllers\UserController::class, 'index']);
            Route::get('users/{id}', [\Workbench\App\Http\Controllers\UserController::class, 'show']);
            Route::post('users', [\Workbench\App\Http\Controllers\UserController::class, 'store']);
            Route::put('users/{user}', [\Workbench\App\Http\Controllers\UserController::class, 'update']);
            Route::delete('users/{id}', [\Workbench\App\Http\Controllers\UserController::class, 'destroy']);
        });

        // Генерируем OpenAPI
        $config = Scramble::getGeneratorConfig('default');
        $config->routes(fn (\Illuminate\Routing\Route $route) => str_starts_with($route->uri, 'api'));

        $generator = app(Generator::class);
        $doc = $generator($config);

        echo json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
};

$testCase->generate();
