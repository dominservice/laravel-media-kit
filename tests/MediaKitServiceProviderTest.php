<?php

namespace Dominservice\MediaKit\Tests;

use Dominservice\MediaKit\MediaKitServiceProvider;
use Illuminate\Database\Migrations\Migrator;
use Orchestra\Testbench\TestCase;

final class MediaKitServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [MediaKitServiceProvider::class];
    }

    public function test_package_migrations_are_registered_without_publishing_them(): void
    {
        $migrationPath = realpath(__DIR__.'/../database/migrations');
        $registeredPaths = array_map(
            static fn (string $path): string|false => realpath($path),
            $this->app->make(Migrator::class)->paths(),
        );

        $this->assertIsString($migrationPath);
        $this->assertContains($migrationPath, $registeredPaths);
    }
}
