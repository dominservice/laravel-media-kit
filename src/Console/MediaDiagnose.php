<?php

namespace Dominservice\MediaKit\Console;

use Illuminate\Console\Command;

class MediaDiagnose extends Command
{
    protected $signature = 'media:diagnose';
    protected $description = 'Diagnostyka środowiska obrazów (GD/Imagick/WebP/AVIF) i konfiguracji pakietu.';

    public function handle(): int
    {
        $checks = [
            'php'           => PHP_VERSION,
            'gd'            => function_exists('gd_info'),
            'imagick'       => class_exists('Imagick'),
            'imagewebp()'   => function_exists('imagewebp'),
            'imageavif()'   => function_exists('imageavif'),
            'webp-convert'  => class_exists('WebPConvert\\WebPConvert'),
        ];

        $this->line('=== ŚRODOWISKO ===');
        foreach ($checks as $name => $val) {
            if (is_bool($val)) {
                $this->line(sprintf("% -14s : %s", $name, $val ? '<info>OK</info>' : '<error>NIE</error>'));
            } else {
                $this->line(sprintf("% -14s : %s", $name, $val));
            }
        }

        $this->line("\n=== KONFIG ===");
        $this->line('disk: ' . config('media-kit.disk'));
        $this->line('mode: ' . config('media-kit.mode'));
        $this->line('formats_priority: ' . implode(',', (array) config('media-kit.formats_priority', [])));
        $this->line('variants: ' . implode(',', array_keys((array) config('media-kit.variants', []))));
        $this->line('responsive.widths: ' . json_encode((array) config('media-kit.responsive.widths', [])));
        $this->line('cdn.base_url: ' . (config('media-kit.cdn.base_url') ?: '-'));
        $this->line('video.mode: ' . config('media-kit.video.mode') . ' / driver: ' . data_get(config('media-kit.video'), 'remote.driver', '-'));

        $this->info("\nDiagnostyka zakończona.");
        return self::SUCCESS;
    }
}
