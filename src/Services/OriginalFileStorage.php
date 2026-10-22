<?php

namespace Dominservice\MediaKit\Services;

use Dominservice\MediaKit\Models\MediaAsset;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OriginalFileStorage
{
    /**
     * @return array{path:string,mime:string,extension:string,size:int,width:int|null,height:int|null,hash:string}
     */
    public static function store(UploadedFile|File|string $file, string $disk): array
    {
        if (is_string($file) && ! is_file($file)) {
            return self::inspectStoredFile($file, $disk);
        }

        $sourcePath = is_string($file) ? $file : $file->getPathname();
        $fallbackExtension = is_string($file)
            ? pathinfo($file, PATHINFO_EXTENSION)
            : ($file instanceof UploadedFile ? $file->getClientOriginalExtension() : $file->getExtension());
        $optimized = OriginalImageOptimizer::optimize($sourcePath);

        try {
            $mime = $optimized['mime'] !== ''
                ? $optimized['mime']
                : ((new File($optimized['path']))->getMimeType() ?: 'application/octet-stream');
            $extension = self::extension($mime, $fallbackExtension);
            $hash = sha1_file($optimized['path']);

            if (! is_string($hash) || $hash === '') {
                throw new RuntimeException('Nie udało się obliczyć skrótu przesyłanego pliku.');
            }

            $path = self::existingPath($disk, $hash, $extension);

            if ($path === null) {
                $directory = 'media/originals/'.substr($hash, 0, 2);
                $fileName = $hash.'.'.$extension;
                $stored = Storage::disk($disk)->putFileAs(
                    $directory,
                    new File($optimized['path']),
                    $fileName,
                );

                if (! is_string($stored) || $stored === '') {
                    throw new RuntimeException('Nie udało się zapisać przesyłanego pliku.');
                }

                $path = $stored;
            }

            return [
                'path' => $path,
                'mime' => $mime,
                'extension' => $extension,
                'size' => (int) Storage::disk($disk)->size($path),
                'width' => $optimized['width'] > 0 ? $optimized['width'] : null,
                'height' => $optimized['height'] > 0 ? $optimized['height'] : null,
                'hash' => $hash,
            ];
        } finally {
            if ($optimized['temporary'] && is_file($optimized['path'])) {
                @unlink($optimized['path']);
            }
        }
    }

    /**
     * @return array{path:string,mime:string,extension:string,size:int,width:int|null,height:int|null,hash:string}
     */
    private static function inspectStoredFile(string $path, string $disk): array
    {
        $storage = Storage::disk($disk);
        $stream = $storage->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Nie można odczytać pliku [{$path}] z dysku [{$disk}].");
        }

        try {
            $context = hash_init('sha1');
            hash_update_stream($context, $stream);
            $hash = hash_final($context);
        } finally {
            fclose($stream);
        }

        [$width, $height] = ImageEngine::dimensions($disk, $path);
        $mime = (string) ($storage->mimeType($path) ?: 'application/octet-stream');

        return [
            'path' => $path,
            'mime' => $mime,
            'extension' => self::extension($mime, pathinfo($path, PATHINFO_EXTENSION)),
            'size' => (int) $storage->size($path),
            'width' => $width,
            'height' => $height,
            'hash' => $hash,
        ];
    }

    private static function existingPath(string $disk, string $hash, string $extension): ?string
    {
        if (! (bool) config('media-kit.originals.deduplicate', true)) {
            return null;
        }

        $storage = Storage::disk($disk);
        $paths = MediaAsset::query()
            ->where('disk', $disk)
            ->where('hash', $hash)
            ->pluck('original_path');

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && $storage->exists($path)) {
                return $path;
            }
        }

        $contentAddressedPath = 'media/originals/'.substr($hash, 0, 2).'/'.$hash.'.'.$extension;

        return $storage->exists($contentAddressedPath) ? $contentAddressedPath : null;
    }

    private static function extension(string $mime, string $fallback): string
    {
        $resolved = match (strtolower($mime)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            default => strtolower(trim($fallback)),
        };

        return preg_match('/^[a-z0-9]{1,12}$/', $resolved) === 1 ? $resolved : 'bin';
    }
}
