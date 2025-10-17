<?php

namespace Dominservice\MediaKit;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Dominservice\MediaKit\Console\MediaCleanup;
use Dominservice\MediaKit\Console\MediaDiagnose;
use Dominservice\MediaKit\Console\MediaRegenerate;

class MediaKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Łączymy domyślny config pakietu z aplikacyjnym
        $this->mergeConfigFrom(__DIR__ . '/../config/media-kit.php', 'media-kit');
    }

    /**
     * Bootstrapping: publikacje, trasy, widoki, komendy, komponenty Blade.
     */
    public function boot(): void
    {
        // Publikacje
        $this->publishes([
            __DIR__.'/../config/media-kit.php' => config_path('media-kit.php'),
        ], 'mediakit-config');

        // Publikacja widoków
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/mediakit'),
        ], 'mediakit-views');

        // Migracje
        if (! class_exists('CreateMediaTables')) {
            $this->publishes([
                __DIR__.'/../database/migrations/2025_10_16_000000_create_media_tables.php' => database_path('migrations/2025_10_16_000000_create_media_tables.php'),
            ], 'mediakit-migrations');
        }

        // Widoki
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mediakit');

        // Trasy
        $this->loadRoutesFrom(__DIR__.'/../routes/media.php');

        // Komponenty Blade (istniejące)
        Blade::component('mediakit::components.media-picture', 'media-picture');
        Blade::component('mediakit::components.media-responsive', 'media-responsive');
        Blade::component('mediakit::components.media-video', 'media-video');

        // NOWE: komponenty dla "kinds"
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
