<?php

namespace Dominservice\MediaKit\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Proste resolvery do starych nazw/ścieżek (np. video_{uuid}.mp4).
 * Dostosuj wzorce do własnych legacy.
 */
class LegacyCompatibility
{
    /**
     * Spróbuj odnaleźć legacy video na dysku (np. content_video) wg wzorca.
     * Zwraca relatywną ścieżkę (do użycia ze Storage::disk(...)->url()) lub null.
     */
    public static function resolveLegacyVideoPath(string $disk, string $modelId): ?string
    {
        // PRZYKŁADOWE wzorce:
        $candidates = [
            "videos/video_{$modelId}.mp4",
            "videos/video_{$modelId}.webm",
            "content/video_{$modelId}.mp4",
            "content/video_{$modelId}.webm",
        ];

        foreach ($candidates as $path) {
            if (Storage::disk($disk)->exists($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Legacy poster — np. poster_{uuid}.jpg/png
     */
    public static function resolveLegacyPosterPath(string $disk, string $modelId): ?string
    {
        $candidates = [
            "videos/poster_{$modelId}.jpg",
            "videos/poster_{$modelId}.png",
            "content/poster_{$modelId}.jpg",
            "content/poster_{$modelId}.png",
        ];
        foreach ($candidates as $path) {
            if (Storage::disk($disk)->exists($path)) {
                return $path;
            }
        }
        return null;
    }
}
