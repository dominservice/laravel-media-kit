<?php

namespace Dominservice\MediaKit;

use Dominservice\MediaKit\Console\MediaCleanup;
use Dominservice\MediaKit\Console\MediaDiagnose;
use Dominservice\MediaKit\Console\MediaRegenerate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class MediaKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/media-kit.php', 'media-kit');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/media-kit.php' => config_path('media-kit.php'),
        ], 'mediakit-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/mediakit'),
        ], 'mediakit-views');

        $this->publishes([
            __DIR__ . '/../database/migrations/2025_10_16_000000_create_media_tables.php' => database_path('migrations/2025_10_16_000000_create_media_tables.php'),
            __DIR__ . '/../database/migrations/2025_10_16_000100_create_media_libraries_table.php' => database_path('migrations/2025_10_16_000100_create_media_libraries_table.php'),
        ], 'mediakit-migrations');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mediakit');
        $this->loadRoutesFrom(__DIR__ . '/../routes/media.php');

        if (config('media-kit.admin.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/admin.php');
        }

        Blade::component('mediakit::components.media-picture', 'media-picture');
        Blade::component('mediakit::components.media-responsive', 'media-responsive');
        Blade::component('mediakit::components.media-video', 'media-video');
        Blade::component('mediakit::components.media-kind-picture', 'media-kind-picture');
        Blade::component('mediakit::components.media-kind-video', 'media-kind-video');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MediaRegenerate::class,
                MediaCleanup::class,
                MediaDiagnose::class,
            ]);
        }
    }
}
