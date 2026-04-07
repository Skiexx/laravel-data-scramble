<?php

declare(strict_types=1);

namespace Skiexx\LaravelDataScramble\Tests;

use Dedoc\Scramble\ScrambleServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Skiexx\LaravelDataScramble\LaravelDataScrambleServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;

class TestCase extends Orchestra
{
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
}
