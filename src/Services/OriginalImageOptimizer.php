<?php

namespace Dominservice\MediaKit\Services;

use GdImage;

class OriginalImageOptimizer
{
    /**
     * @return array{path:string,mime:string,width:int,height:int,temporary:bool}
     */
    public static function optimize(string $sourcePath): array
    {
        $imageInfo = @getimagesize($sourcePath);

        if (! is_array($imageInfo)) {
            return self::unchanged($sourcePath, '', 0, 0);
        }

        [$sourceWidth, $sourceHeight, $type] = $imageInfo;
        $mime = (string) ($imageInfo['mime'] ?? '');

        if (! (bool) config('media-kit.originals.enabled', true)
            || ! extension_loaded('gd')
            || ! self::supportsType((int) $type)) {
            return self::unchanged($sourcePath, $mime, (int) $sourceWidth, (int) $sourceHeight);
        }

        $maxDimension = max(1, (int) config('media-kit.originals.max_dimension', 2560));
        $maxFileSize = max(0, (int) config('media-kit.originals.max_file_size_kb', 2048)) * 1024;
        $sourceSize = (int) (@filesize($sourcePath) ?: 0);
        $needsOptimization = max((int) $sourceWidth, (int) $sourceHeight) > $maxDimension
            || ($maxFileSize > 0 && $sourceSize > $maxFileSize);

        if (! $needsOptimization) {
            return self::unchanged($sourcePath, $mime, (int) $sourceWidth, (int) $sourceHeight);
        }

        $source = self::createImage($sourcePath, (int) $type);

        if (! $source instanceof GdImage) {
            return self::unchanged($sourcePath, $mime, (int) $sourceWidth, (int) $sourceHeight);
        }

        $source = self::applyExifOrientation($source, $sourcePath, (int) $type);
        $orientedWidth = imagesx($source);
        $orientedHeight = imagesy($source);
        $scale = min(1, $maxDimension / max($orientedWidth, $orientedHeight));
        $targetWidth = max(1, (int) round($orientedWidth * $scale));
        $targetHeight = max(1, (int) round($orientedHeight * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if (in_array((int) $type, [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_AVIF], true)) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $orientedWidth,
            $orientedHeight,
        );

        $temporaryPath = tempnam(sys_get_temp_dir(), 'mediakit_original_');

        if (! is_string($temporaryPath)
            || ! self::writeImage($target, $temporaryPath, (int) $type)) {
            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }

            imagedestroy($source);
            imagedestroy($target);

            return self::unchanged($sourcePath, $mime, (int) $sourceWidth, (int) $sourceHeight);
        }

        imagedestroy($source);
        imagedestroy($target);

        $optimizedSize = (int) (@filesize($temporaryPath) ?: 0);
        $onlyIfSmaller = (bool) config('media-kit.originals.only_if_smaller', true);

        if ($optimizedSize === 0 || ($onlyIfSmaller && $sourceSize > 0 && $optimizedSize >= $sourceSize)) {
            @unlink($temporaryPath);

            return self::unchanged($sourcePath, $mime, (int) $sourceWidth, (int) $sourceHeight);
        }

        return [
            'path' => $temporaryPath,
            'mime' => $mime,
            'width' => $targetWidth,
            'height' => $targetHeight,
            'temporary' => true,
        ];
    }

    /** @return array{path:string,mime:string,width:int,height:int,temporary:bool} */
    private static function unchanged(string $path, string $mime, int $width, int $height): array
    {
        return compact('path', 'mime', 'width', 'height') + ['temporary' => false];
    }

    private static function supportsType(int $type): bool
    {
        return match ($type) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'),
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') && function_exists('imagepng'),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
            IMAGETYPE_AVIF => function_exists('imagecreatefromavif') && function_exists('imageavif'),
            default => false,
        };
    }

    private static function createImage(string $path, int $type): GdImage|false
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_AVIF => @imagecreatefromavif($path),
            default => false,
        };
    }

    private static function writeImage(GdImage $image, string $path, int $type): bool
    {
        return match ($type) {
            IMAGETYPE_JPEG => self::writeJpeg($image, $path),
            IMAGETYPE_PNG => imagepng(
                $image,
                $path,
                max(-1, min(9, (int) config('media-kit.originals.png_compression', 7))),
            ),
            IMAGETYPE_WEBP => imagewebp(
                $image,
                $path,
                max(0, min(100, (int) config('media-kit.originals.webp_quality', 82))),
            ),
            IMAGETYPE_AVIF => imageavif(
                $image,
                $path,
                max(0, min(100, (int) config('media-kit.originals.avif_quality', 55))),
            ),
            default => false,
        };
    }

    private static function writeJpeg(GdImage $image, string $path): bool
    {
        imageinterlace($image, (bool) config('media-kit.progressive_jpeg', true));

        return imagejpeg(
            $image,
            $path,
            max(0, min(100, (int) config('media-kit.originals.jpeg_quality', 85))),
        );
    }

    private static function applyExifOrientation(GdImage $image, string $path, int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, in_array($orientation, [2, 5], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }

        $degrees = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($degrees === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $degrees, 0);

        if (! $rotated instanceof GdImage) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }
}
