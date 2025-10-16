<?php

namespace Dominservice\MediaKit\Services;

use Illuminate\Support\Facades\Config;

/**
 * Nakładanie filtrów na obraz (GD).
 * Filtry działają "in place" na obrazie wynikowym po resize, przed zapisem.
 *
 * Obsługiwane:
 * - grayscale: bool
 * - blur: int (1..5) — powtarzany Gaussian blur
 * - watermark_image: [
 *     path: string,
 *     position: string ('top-left','top-right','bottom-left','bottom-right','center'),
 *     opacity: float 0..1,
 *     scale: float 0..1 (proporcja szerokości obrazka),
 *     offset_x: int,
 *     offset_y: int
 *   ]
 * - watermark_text (prosty): [
 *     text: string,
 *     size: int (px),
 *     color: string '#RRGGBB',
 *     position: jw.w jak wyżej,
 *     opacity: float 0..1,
 *     offset_x: int,
 *     offset_y: int,
 *     ttf: string|null (ścieżka do czcionki; jeśli null — fallback do imagestring)
 *   ]
 */
class ImageFilters
{
    /**
     * @param \GdImage|resource $img  (modyfikowany w miejscu)
     * @param array $filters Lista filtrów/presetów:
     *  - ['grayscale'] (preset)
     *  - [['blur' => 2], ['watermark_image' => [...]]]
     */
    public static function apply($img, array $filters): void
    {
        if (!$img || empty($filters)) {
            return;
        }

        // Rozwiąż nazwy presetów do listy filtrów
        $resolved = [];
        foreach ($filters as $f) {
            if (is_string($f)) {
                $preset = Config::get("media-kit.filters_presets.$f");
                if (is_array($preset)) {
                    foreach ($preset as $row) {
                        $resolved[] = $row;
                    }
                }
            } elseif (is_array($f)) {
                $resolved[] = $f;
            }
        }

        // Zastosuj po kolei
        foreach ($resolved as $row) {
            // grayscale
            if (isset($row['grayscale'])) {
                @imagefilter($img, IMG_FILTER_GRAYSCALE);
            }

            // blur
            if (isset($row['blur'])) {
                $times = max(1, (int) $row['blur']);
                for ($i = 0; $i < $times; $i++) {
                    @imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
                }
            }

            // watermark_image
            if (isset($row['watermark_image']) && is_array($row['watermark_image'])) {
                self::applyWatermarkImage($img, $row['watermark_image']);
            }

            // watermark_text
            if (isset($row['watermark_text']) && is_array($row['watermark_text'])) {
                self::applyWatermarkText($img, $row['watermark_text']);
            }
        }
    }

    protected static function applyWatermarkImage($img, array $cfg): void
    {
        $path     = (string) ($cfg['path'] ?? '');
        $position = (string) ($cfg['position'] ?? 'bottom-right');
        $opacity  = (float)  ($cfg['opacity'] ?? 0.35);
        $scale    = (float)  ($cfg['scale'] ?? 0.2);
        $ox       = (int)    ($cfg['offset_x'] ?? 12);
        $oy       = (int)    ($cfg['offset_y'] ?? 12);

        if ($path === '' || !is_file($path)) {
            return;
        }

        // Wczytaj watermark (preferuj PNG z alpha)
        $wm = @imagecreatefrompng($path);
        if (!$wm) {
            $info = @getimagesize($path);
            if ($info && isset($info[2])) {
                $type = $info[2];
                $wm = match ($type) {
                    IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
                    IMAGETYPE_PNG  => @imagecreatefrompng($path),
                    IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
                    default        => null,
                };
            }
        }
        if (!$wm) return;

        $imgW = imagesx($img);
        $imgH = imagesy($img);
        $wmW  = imagesx($wm);
        $wmH  = imagesy($wm);

        // Skala względem szerokości obrazu (np. 0.2 = 20% szerokości)
        $targetW = max(1, (int) round($imgW * max(0.01, min(1.0, $scale))));
        $ratio   = $targetW / $wmW;
        $targetH = max(1, (int) round($wmH * $ratio));

        // Przeskaluj watermark do docelowych wymiarów
        $wmResized = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($wmResized, false);
        imagesavealpha($wmResized, true);
        $transparent = imagecolorallocatealpha($wmResized, 0, 0, 0, 127);
        imagefilledrectangle($wmResized, 0, 0, $targetW, $targetH, $transparent);
        imagecopyresampled($wmResized, $wm, 0, 0, 0, 0, $targetW, $targetH, $wmW, $wmH);
        imagedestroy($wm);

        // Pozycja
        [$dstX, $dstY] = self::computePosition($imgW, $imgH, $targetW, $targetH, $position, $ox, $oy);

        // Opacity: 0..1 -> 0..127 (gd-alpha)
        $alpha = (int) round((1 - max(0, min(1, $opacity))) * 127);

        // Nanieś (zachowując alpha)
        imagealphablending($img, true);
        self::imageCopyMergeAlpha($img, $wmResized, $dstX, $dstY, 0, 0, $targetW, $targetH, 127 - $alpha);
        imagedestroy($wmResized);
    }

    protected static function applyWatermarkText($img, array $cfg): void
    {
        $text     = (string) ($cfg['text'] ?? '');
        if ($text === '') return;

        $size     = (int)   ($cfg['size'] ?? 16);
        $colorHex = (string)($cfg['color'] ?? '#000000');
        $position = (string)($cfg['position'] ?? 'bottom-right');
        $opacity  = (float) ($cfg['opacity'] ?? 0.5);
        $ox       = (int)   ($cfg['offset_x'] ?? 12);
        $oy       = (int)   ($cfg['offset_y'] ?? 12);
        $ttf      = $cfg['ttf'] ?? null;

        [$r,$g,$b] = self::hexToRgb($colorHex);
        $alpha = (int) round((1 - max(0, min(1, $opacity))) * 127);
        $col   = imagecolorallocatealpha($img, $r, $g, $b, $alpha);

        $imgW = imagesx($img);
        $imgH = imagesy($img);

        if ($ttf && is_file($ttf) && function_exists('imagettfbbox')) {
            // Zmierz
            $bbox = imagettfbbox($size, 0, $ttf, $text);
            $tw = abs($bbox[2] - $bbox[0]);
            $th = abs($bbox[7] - $bbox[1]);
            [$x, $y] = self::computePosition($imgW, $imgH, $tw, $th, $position, $ox, $oy);
            // TTF liczy baseline; przesuwamy Y o wysokość
            $y += $th;
            imagettftext($img, $size, 0, $x, $y, $col, $ttf, $text);
        } else {
            // Fallback: imagestring (monospace)
            $font = 5; // największy wbudowany
            $tw = imagefontwidth($font) * strlen($text);
            $th = imagefontheight($font);
            [$x, $y] = self::computePosition($imgW, $imgH, $tw, $th, $position, $ox, $oy);
            imagestring($img, $font, $x, $y, $text, $col);
        }
    }

    protected static function computePosition(int $imgW, int $imgH, int $w, int $h, string $pos, int $ox, int $oy): array
    {
        switch (strtolower($pos)) {
            case 'top-left':     return [0 + $ox, 0 + $oy];
            case 'top-right':    return [$imgW - $w - $ox, 0 + $oy];
            case 'bottom-left':  return [0 + $ox, $imgH - $h - $oy];
            case 'center':       return [ (int)(($imgW - $w)/2), (int)(($imgH - $h)/2) ];
            case 'bottom-right':
            default:             return [$imgW - $w - $ox, $imgH - $h - $oy];
        }
    }

    /**
     * Kopiowanie z zachowaniem kanału alpha i przezroczystości (dla PNG/WebP).
     * $pct: 0..127 (im mniejszy, tym bardziej widoczne; 127 = całkowicie przezroczyste)
     */
    protected static function imageCopyMergeAlpha($dst, $src, int $dstX, int $dstY, int $srcX, int $srcY, int $w, int $h, int $pct): void
    {
        // uproszczony merge alpha: rysujemy piksel po pikselu
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgba = imagecolorat($src, $x, $y);
                $a = ($rgba & 0x7F000000) >> 24;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                // miks globalnego pct z lokalnym alpha (a: 0..127)
                $alpha = min(127, max(0, $a + $pct));
                $color = imagecolorallocatealpha($dst, $r, $g, $b, $alpha);
                imagesetpixel($dst, $dstX + $x, $dstY + $y, $color);
            }
        }
    }

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
