<?php

namespace Dominservice\MediaKit\Tests;

use Dominservice\MediaKit\MediaKitServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            MediaKitServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // Dysk "public" kierujemy do sandboxu testowego
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root'   => storage_path('framework/testing/disks/public'),
            'url'    => '/storage',
            'visibility' => 'public',
        ]);

        // Konfiguracja pakietu na czas testów
        $app['config']->set('media-kit.disk', 'public');
        $app['config']->set('media-kit.mode', 'eager'); // domyślnie eager
        $app['config']->set('media-kit.formats_priority', ['jpeg']); // upraszczamy testy
        $app['config']->set('media-kit.enabled_formats_per_variant', [
            'thumb' => ['jpeg'],
            '*'     => ['jpeg'],
        ]);
        $app['config']->set('media-kit.variants', [
            'thumb' => ['fit' => [160, 160]],
            'md'    => ['width' => 600, 'filters' => ['grayscale' => true]],
        ]);
        $app['config']->set('media-kit.cache_headers', []);
        $app['config']->set('media-kit.cache.enable_reuse', true);
        $app['config']->set('media-kit.cache.only_same_disk', true);
        $app['config']->set('media-kit.responsive', [
            'order'  => ['thumb','md'],
            'widths' => ['thumb' => 160, 'md' => 600],
            'default_sizes' => '100vw',
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        // Migracje pakietu
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // Testowa tabela posts
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
