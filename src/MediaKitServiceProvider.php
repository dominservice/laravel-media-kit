<?php

namespace Dominservice\MediaKit;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Dominservice\MediaKit\Console\MediaCleanup;
use Dominservice\MediaKit\Console\MediaDiagnose;
use Dominservice\MediaKit\Console\MediaRegenerate;

class MediaKitServiceProvider extends ServiceProvider
{
    /**
     * Rejestracja usług / configu.
     */
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
        // Publikacja configu
        $this->publishes([
            __DIR__ . '/../config/media-kit.php' => $this->configPath('media-kit.php'),
        ], 'mediakit-config');

        // Publikacja widoków
        $this->publishes([
            __DIR__ . '/../resources/views' => $this->resourcePath('views/vendor/mediakit'),
        ], 'mediakit-views');

        // Publikacja migracji (unikamy duplikatów)
        if (!class_exists('CreateMediaTables')) {
            $this->publishes([
                __DIR__ . '/../database/migrations/2025_10_16_000000_create_media_tables.php'
                => $this->databasePath('migrations/2025_10_16_000000_create_media_tables.php'),
            ], 'mediakit-migrations');
        }

        // Rejestracja widoków pakietu
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mediakit');

        // Rejestracja tras
        $this->loadRoutesFrom(__DIR__ . '/../routes/media.php');

        // Komponenty Blade
        // (Zarejestruj wszystkie, nawet jeśli nie użyjesz wszystkich od razu)
        if (class_exists(Blade::class)) {
            Blade::component('mediakit::components.media-picture', 'media-picture');
            Blade::component('mediakit::components.media-responsive', 'media-responsive');
            Blade::component('mediakit::components.media-video', 'media-video');
        }

        // Komendy Artisan (tylko w CLI)
        if ($this->app->runningInConsole()) {
            $this->commands([
                MediaRegenerate::class,
                MediaCleanup::class,
                MediaDiagnose::class,
            ]);
        }
    }

    /**
     * Zgodność wsteczna: helpery ścieżek działające w L9–L12.
     */
    protected function configPath(string $path): string
    {
        // Laravel 9–12: config_path istnieje; na wszelki wypadek fallback
        return function_exists('config_path') ? config_path($path) : $this->app->basePath('config/' . $path);
    }

    protected function resourcePath(string $path): string
    {
        return function_exists('resource_path') ? resource_path($path) : $this->app->basePath('resources/' . $path);
    }

    protected function databasePath(string $path): string
    {
        return function_exists('database_path') ? database_path($path) : $this->app->basePath('database/' . $path);
    }
}
