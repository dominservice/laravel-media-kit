<?php

namespace Dominservice\MediaKit\Http\Controllers;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Services\ImageEngine;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Serwuje (lub w trybie lazy: generuje i serwuje) wariant obrazu.
     *
     * GET /media/{asset}/{variant}/{filename?}
     */
    public function show(string $asset, string $variant, ?string $filename = null): StreamedResponse
    {
        /** @var MediaAsset $assetModel */
        $assetModel = MediaAsset::query()->findOrFail($asset);

        $resolvedVariant = $this->resolveVariantName($variant);

        // Czy taki wariant jest zdefiniowany w configu?
        $variantRules = Config::get("media-kit.variants.{$resolvedVariant}");
        if (!$variantRules) {
            abort(404, "Variant '{$variant}' not configured");
        }

        // W jakiej kolejności próbujemy formatów?
        $formatOrder = (array) Config::get('media-kit.formats_priority', ['avif','webp','jpeg','png']);

        // 1) Spróbuj znaleźć istniejący wariant w preferowanej kolejności
        foreach ($formatOrder as $fmt) {
            $found = $assetModel->variants()->where(['name' => $resolvedVariant, 'format' => $fmt])->first();
            if ($found) {
                return $this->streamVariant($found->disk, $found->path, $fmt);
            }
        }

        // 2) Tryb lazy? Spróbuj wygenerować pierwszy możliwy
        if (Config::get('media-kit.mode') === 'lazy') {
            // które formaty są włączone dla tego wariantu?
            $enabled = Config::get("media-kit.enabled_formats_per_variant.{$variant}")
                ?? Config::get('media-kit.enabled_formats_per_variant.*')
                ?? $formatOrder;

            foreach ($enabled as $fmt) {
                $generated = ImageEngine::generateVariant(
                    $assetModel,
                    $resolvedVariant,
                    $variantRules,
                    $fmt,
                    $assetModel->disk,
                    $assetModel->original_path
                );
                if ($generated) {
                    return $this->streamVariant($generated->disk, $generated->path, $fmt);
                }
            }
        }

        // Brak wariantu i brak możliwości wygenerowania
        abort(404);
    }

    private function resolveVariantName(string $requestedVariant): string
    {
        $requestedVariant = trim($requestedVariant);
        $configuredVariants = array_values(array_filter(array_map(
            static fn (mixed $variant): string => trim((string) $variant),
            array_keys((array) Config::get('media-kit.variants', []))
        )));

        if ($requestedVariant === '' || $configuredVariants === []) {
            return $requestedVariant;
        }

        if (in_array($requestedVariant, $configuredVariants, true)) {
            return $requestedVariant;
        }

        $requestedLower = strtolower($requestedVariant);

        $previewCandidates = ['thumb', 'sm', 'desktop_thumb', 'tablet_thumb', 'mobile_thumb'];
        $fullCandidates = ['lg', 'md', 'xl', 'desktop_full', 'tablet_full', 'mobile_full', 'desktop', 'tablet', 'mobile'];

        if (in_array($requestedLower, ['thumb', 'thumbnail', 'sm'], true)) {
            return $this->firstMatchingVariant($configuredVariants, $previewCandidates, 'thumb');
        }

        if (in_array($requestedLower, ['lg', 'md', 'xl', 'full', 'preview'], true)) {
            return $this->firstMatchingVariant($configuredVariants, $fullCandidates, 'full', true);
        }

        return $configuredVariants[0];
    }

    /**
     * @param array<int, string> $configuredVariants
     * @param array<int, string> $preferredNames
     */
    private function firstMatchingVariant(array $configuredVariants, array $preferredNames, string $containsNeedle, bool $preferLast = false): string
    {
        foreach ($preferredNames as $preferredName) {
            if (in_array($preferredName, $configuredVariants, true)) {
                return $preferredName;
            }
        }

        $containsMatches = array_values(array_filter(
            $configuredVariants,
            static fn (string $variant): bool => str_contains(strtolower($variant), strtolower($containsNeedle))
        ));

        if ($containsMatches !== []) {
            return $preferLast ? $containsMatches[array_key_last($containsMatches)] : $containsMatches[0];
        }

        return $preferLast ? $configuredVariants[array_key_last($configuredVariants)] : $configuredVariants[0];
    }

    /**
     * Strumieniuje wskazany plik z dysku ze stosownymi nagłówkami i mime.
     */
    protected function streamVariant(string $disk, string $path, string $format): StreamedResponse
    {
        $stream = Storage::disk($disk)->readStream($path);
        if (!$stream) {
            abort(404);
        }

        // Ustal mime po formacie docelowym
        $mime = match (strtolower($format)) {
            'avif' => 'image/avif',
            'webp' => 'image/webp',
            'png'  => 'image/png',
            default => 'image/jpeg',
        };

        // Nagłówki cache (długie, immutable)
        $headers = array_merge([
            'Content-Type' => $mime,
        ], (array) Config::get('media-kit.cache_headers', [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]));

        return Response::stream(function () use ($stream) {
            // Przekaż surowy strumień do klienta
            fpassthru($stream);
        }, 200, $headers);
    }
}
