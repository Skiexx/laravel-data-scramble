<?php

/**
 * Скрипт для запуска тестового Laravel-приложения с Scramble UI.
 *
 * Использование:
 *   php workbench/serve.php          — запустить сервер на 0.0.0.0:8000
 *   php workbench/serve.php --json   — вывести OpenAPI JSON и выйти
 *
 * Scramble UI: http://localhost:8000/docs/api
 * OpenAPI JSON: http://localhost:8000/docs/api.json
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Orchestra\Testbench\Foundation\Application;
use Skiexx\LaravelDataScramble\LaravelDataScrambleServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;

$app = Application::create(
    basePath: __DIR__ . '/../vendor/orchestra/testbench-core/laravel',
    options: ['extra' => ['providers' => [
        LaravelDataServiceProvider::class,
        ScrambleServiceProvider::class,
        LaravelDataScrambleServiceProvider::class,
    ]]],
);

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Загружаем маршруты
require __DIR__ . '/routes/api.php';

// Регистрируем маршруты Scramble UI
Scramble::configure('default')
    ->routes(fn (\Illuminate\Routing\Route $route) => str_starts_with($route->uri, 'api'));

Scramble::registerUiRoute('docs/api');
Scramble::registerJsonSpecificationRoute('docs/api.json');

// Режим: вывести JSON или запустить сервер
if (in_array('--json', $argv ?? [])) {
    $generator = $app->make(\Dedoc\Scramble\Generator::class);
    $config = Scramble::getGeneratorConfig('default');
    $doc = $generator($config);
    echo json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit(0);
}

// Запуск dev-сервера
echo "Scramble UI: http://localhost:8000/docs/api\n";
echo "OpenAPI JSON: http://localhost:8000/docs/api.json\n";
echo "Press Ctrl+C to stop.\n\n";

$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

// Обработка HTTP-запросов через встроенный PHP-сервер
$request = \Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
