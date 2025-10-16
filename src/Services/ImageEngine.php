<?php

namespace Dominservice\MediaKit\Services;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Models\MediaVariant;
use Dominservice\MediaKit\Services\ImageFilters;
use Dominservice\MediaKit\Services\VariantReuse;
use Dominservice\MediaKit\Support\PathHelper;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use WebPConvert\WebPConvert;

/**
 * Silnik generowania wariantów obrazów.
 * Zgodny z Laravel 9–12, przyjazny dla hostingu współdzielonego:
 * - GD/Imagick jeśli są dostępne
 * - fallback do webp-convert dla WebP
 * - AVIF jeśli imageavif() dostępne, inaczej fallback (zapis jako WebP pod rozszerzeniem avif)
 */
class ImageEngine
{
    /**
     * Odczytaj wymiary obrazu (jeśli możliwe).
     *
     * @return array{0:int|null,1:int|null}
     */
    public static function dimensions(string $disk, string $path): array
    {
        try {
            $abs = Storage::disk($disk)->path($path);
            [$w, $h] = @getimagesize($abs) ?: [null, null];
            return [$w ? (int)$w : null, $h ? (int)$h : null];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    /**
     * Wygeneruj wszystkie warianty zdefiniowane w configu.
     */
    public static function generateAllVariants(MediaAsset $asset): void
    {
        $variants = (array) Config::get('media-kit.variants', []);
        $formatsPerVariant = (array) Config::get('media-kit.enabled_formats_per_variant', []);
        $fallbackFormats = (array) Config::get('media-kit.formats_priority', ['avif','webp','jpeg','png']);

        foreach ($variants as $name => $rules) {
            $enabled = $formatsPerVariant[$name] ?? ($formatsPerVariant['*'] ?? $fallbackFormats);
            foreach ($enabled as $format) {
                static::generateVariant(
                    $asset,
                    (string) $name,
                    (array) $rules,
                    (string) $format,
                    $asset->disk,
                    $asset->original_path
                );
            }
        }
    }

    /**
     * Wygeneruj pojedynczy wariant (jeśli nie istnieje).
     */
    public static function generateVariant(
        MediaAsset $asset,
        string $name,
        array $rules,
        string $format,
        string $disk,
        string $sourcePath
    ): ?MediaVariant {
        // 0) Jeśli istnieje – zwróć
        $exists = $asset->variants()->where(['name' => $name, 'format' => $format])->first();
        if ($exists) {
            return $exists;
        }

        // 1) PRÓBA REUSE (kopiowanie z innego assetu o tym samym hash'u)
        if ($reused = VariantReuse::tryReuse($asset, $name, $format)) {
            return $reused;
        }

        // 2) Standardowa ścieżka – generacja
        $quality   = Config::get("media-kit.default_quality.$format");
        $targetRel = PathHelper::variantPath($asset, $name, $format);
        $targetAbs = Storage::disk($disk)->path($targetRel);
        $sourceAbs = Storage::disk($disk)->path($sourcePath);

        @mkdir(dirname($targetAbs), 0775, true);

        [$w, $h] = self::applyTransform($sourceAbs, $targetAbs, $rules, $format, $quality);
        $size = file_exists($targetAbs) ? filesize($targetAbs) : null;

        return $asset->variants()->create([
            'name'         => $name,
            'format'       => $format,
            'disk'         => $disk,
            'path'         => $targetRel,
            'width'        => $w,
            'height'       => $h,
            'quality'      => $quality,
            'size'         => $size,
            'generated_at' => now(),
            'meta'         => null,
        ]);
    }

    /**
     * Realna konwersja (resize + zapis do formatu).
     * Minimalna liczba zależności; preferuje GD, ale nie wymaga go do WebP (fallback).
     *
     * @return array{0:int|null,1:int|null} - wymiary pliku docelowego
     */
    protected static function applyTransform(string $srcAbs, string $dstAbs, array $rules, string $format, ?int $quality): array
    {
        [$w, $h, $type] = @getimagesize($srcAbs);
        if (!$w || !$h) {
            // nie rozpoznano obrazu — skopiuj jak jest
            @copy($srcAbs, $dstAbs);
            return [null, null];
        }

        // docelowe wymiary
        $targetW = $rules['width']  ?? null;
        $targetH = $rules['height'] ?? null;
        if (isset($rules['fit']) && is_array($rules['fit'])) {
            [$targetW, $targetH] = $rules['fit'];
        }
        [$newW, $newH] = self::fitSize((int)$w, (int)$h, $targetW ? (int)$targetW : null, $targetH ? (int)$targetH : null);

        // wczytaj źródło (GD)
        $srcImg = self::imageCreateFrom($srcAbs, (int)$type);
        if (!$srcImg) {
            @copy($srcAbs, $dstAbs);
            return [$w, $h];
        }

        $dstImg = imagecreatetruecolor($newW, $newH);

        // przezroczystość / tło
        $transparentOut = in_array(strtolower($format), ['png','webp','avif'], true);
        if ($transparentOut) {
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
            imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $transparent);
        } else {
            $bgHex   = (string) Config::get('media-kit.background_for_transparent_to_jpeg', '#ffffff');
            [$r,$g,$b] = self::hexToRgb($bgHex);
            $bgColor = imagecolorallocate($dstImg, $r, $g, $b);
            imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $bgColor);
        }

        // resize
        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);

        // Filtry (po resize, przed zapisem)
        if (isset($rules['filters']) && is_array($rules['filters'])) {
            ImageFilters::apply($dstImg, $rules['filters']);
        }

        // zapis
        switch (strtolower($format)) {
            case 'jpeg':
            case 'jpg':
                imageinterlace($dstImg, Config::get('media-kit.progressive_jpeg') ? 1 : 0);
                imagejpeg($dstImg, $dstAbs, $quality ?? 75);
                break;

            case 'png':
                imagepng($dstImg, $dstAbs); // level kompresji zostawiamy domyślny
                break;

            case 'webp':
                if (function_exists('imagewebp')) {
                    imagewebp($dstImg, $dstAbs, $quality ?? 75);
                } else {
                    // fallback: webp-convert poradzi sobie bez GD::imagewebp
                    WebPConvert::convert($srcAbs, $dstAbs, [
                        'quality'      => $quality ?? 75,
                        'max-quality'  => $quality ?? 75,
                        'converters'   => ['cwebp', 'imagick', 'gd', 'vips', 'ewww'],
                    ]);
                }
                break;

            case 'avif':
                if (function_exists('imageavif')) {
                    imageavif($dstImg, $dstAbs, $quality ?? 45);
                } else {
                    // fallback: wygeneruj WebP i zapisz pod docelowym rozszerzeniem
                    $tmp = $dstAbs . '.tmp.webp';
                    if (function_exists('imagewebp')) {
                        imagewebp($dstImg, $tmp, 80);
                    } else {
                        WebPConvert::convert($srcAbs, $tmp, ['quality' => 80]);
                    }
                    @rename($tmp, $dstAbs);
                }
                break;

            default:
                // nieznany – skopiuj oryginał
                @copy($srcAbs, $dstAbs);
                break;
        }

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        [$nw, $nh] = @getimagesize($dstAbs) ?: [$newW, $newH];
        return [(int)$nw, (int)$nh];
    }

    /**
     * Oblicz docelowe wymiary przy zachowaniu proporcji (contain). Bez upscalowania, chyba że w configu 'upscale' = true.
     *
     * @return array{0:int,1:int}
     */
    protected static function fitSize(int $w, int $h, ?int $tw, ?int $th): array
    {
        $upscale = (bool) Config::get('media-kit.upscale', false);

        if (!$tw && !$th) {
            return [$w, $h];
        }

        if ($tw && $th) {
            $scale = min($tw / $w, $th / $h);
            if ($scale > 1 && !$upscale) {
                return [$w, $h];
            }
            return [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        }

        if ($tw) {
            if ($tw >= $w && !$upscale) {
                return [$w, $h];
            }
            $scale = $tw / $w;
            return [$tw, max(1, (int) round($h * $scale))];
        }

        // tylko wysokość
        if ($th >= $h && !$upscale) {
            return [$w, $h];
        }
        $scale = $th / $h;
        return [max(1, (int) round($w * $scale)), $th];
    }

    /**
     * Wczytaj obraz przez GD w zależności od typu.
     * @return resource|\GdImage|false
     */
    protected static function imageCreateFrom(string $path, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : @imagecreatefromjpeg($path),
            default        => @imagecreatefromstring(@file_get_contents($path)),
        };
    }

    /**
     * #rrggbb -> [r,g,b]
     */
    protected static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
