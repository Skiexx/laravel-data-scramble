<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Illuminate\Http\Resources\Json\JsonResource;
use Skiexx\LaravelDataScramble\Extensions\LaravelDataTypeToSchemaExtension;
use Skiexx\LaravelDataScramble\Extensions\ResponseDataOperationExtension;
use Skiexx\LaravelDataScramble\Extensions\StripRoutePrefixExtension;
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
                    fn (\Dedoc\Scramble\Configuration\OperationTransformers $transformers) => $transformers
                        ->prepend(StripRoutePrefixExtension::class)
                        ->append(ResponseDataOperationExtension::class)
                )
                ->afterOpenApiGenerated(function (OpenApi $openApi): void {
                    $this->removeJsonResourceSchema($openApi);
                });
        }
    }

    /**
     * Удаляет бесполезную схему JsonResource из components/schemas.
     *
     * Scramble генерирует JsonResource: { type: string } для анонимных JsonResource.
     * Также заменяет $ref-ссылки на JsonResource в responses на generic object.
     */
    private function removeJsonResourceSchema(OpenApi $openApi): void
    {
        $jsonResourceNames = [JsonResource::class, 'JsonResource'];

        foreach ($jsonResourceNames as $name) {
            if ($openApi->components->hasSchema($name)) {
                $openApi->components->removeSchema($name);
            }
        }

        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                foreach ($operation->responses as $i => $response) {
                    if (!$response instanceof Response) {
                        continue;
                    }

                    if (str_contains($response->description, 'JsonResource')) {
                        $response->setDescription('');
                    }

                    foreach ($response->content as $mediaType => $schema) {
                        if (!$schema instanceof Schema) {
                            continue;
                        }

                        $this->replaceJsonResourceRefs($schema);
                    }
                }
            }
        }
    }

    /**
     * Заменяет $ref на JsonResource внутри schema на generic object.
     *
     * Рекурсивно проходит по properties ObjectType,
     * заменяя Reference на JsonResource на пустой ObjectType.
     */
    private function replaceJsonResourceRefs(Schema $schema): void
    {
        $type = $schema->type;

        if (!$type instanceof OpenApiObjectType) {
            return;
        }

        foreach ($type->properties as $name => $propertyType) {
            if (
                $propertyType instanceof Reference
                && ($propertyType->fullName === JsonResource::class || $propertyType->fullName === 'JsonResource')
            ) {
                $type->properties[$name] = new OpenApiObjectType();
            }
        }
    }
}
