<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble;

use Dedoc\Scramble\Scramble;
use Skiexx\LaravelDataScramble\Extensions\LaravelDataTypeToSchemaExtension;
use Skiexx\LaravelDataScramble\Extensions\ResponseDataOperationExtension;
use Skiexx\LaravelDataScramble\Extractors\DataParametersExtractor;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Сервис-провайдер пакета laravel-data-scramble.
 *
 * Регистрирует TypeToSchemaExtension для парсинга Data-классов,
 * DataParametersExtractor для обработки входных параметров контроллеров
 * и ResponseDataOperationExtension для атрибута #[ResponseData].
 */
class LaravelDataScrambleServiceProvider extends PackageServiceProvider
{
    /** Конфигурация пакета: имя и файл конфигурации. */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-data-scramble')
            ->hasConfigFile();
    }

    /**
     * Регистрация всех расширений Scramble после загрузки пакета.
     *
     * Выполняется только если auto_register включён в конфигурации.
     */
    public function packageBooted(): void
    {
        if (config('laravel-data-scramble.auto_register', true)) {
            Scramble::registerExtension(LaravelDataTypeToSchemaExtension::class);

            Scramble::configure()
                ->withParametersExtractors(
                    fn (\Dedoc\Scramble\Configuration\ParametersExtractors $extractors) => $extractors->prepend(DataParametersExtractor::class)
                )
                ->withOperationTransformers(
                    fn (\Dedoc\Scramble\Configuration\OperationTransformers $transformers) => $transformers->append(ResponseDataOperationExtension::class)
                );
        }
    }
}
