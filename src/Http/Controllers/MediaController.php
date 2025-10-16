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

        // Czy taki wariant jest zdefiniowany w configu?
        $variantRules = Config::get("media-kit.variants.{$variant}");
        if (!$variantRules) {
            abort(404, "Variant '{$variant}' not configured");
        }

        // W jakiej kolejności próbujemy formatów?
        $formatOrder = (array) Config::get('media-kit.formats_priority', ['avif','webp','jpeg','png']);

        // 1) Spróbuj znaleźć istniejący wariant w preferowanej kolejności
        foreach ($formatOrder as $fmt) {
            $found = $assetModel->variants()->where(['name' => $variant, 'format' => $fmt])->first();
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
                    $variant,
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
