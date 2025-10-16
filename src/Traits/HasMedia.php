<?php

namespace Dominservice\MediaKit\Traits;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Services\ImageEngine;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasMedia
{
    /**
     * Relacja: wszystkie media przypięte do modelu.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'model');
    }

    /**
     * Dodaj plik (UploadedFile|string|File) do kolekcji i ewentualnie wygeneruj warianty (tryb eager).
     *
     * @param  UploadedFile|string|File  $file  - instancja UploadedFile, File lub ścieżka do istniejącego pliku na dysku aplikacji
     * @param  string $collection                - nazwa kolekcji (np. 'featured', 'gallery')
     */
    public function addMedia(UploadedFile|string|File $file, string $collection = 'default'): MediaAsset
    {
        $disk = (string) config('media-kit.disk', 'public');

        // 1) Zapis oryginału
        if ($file instanceof UploadedFile) {
            $storedPath = $file->store('media/originals/' . date('Y/m'), $disk);
            $mime = $file->getClientMimeType();
            $ext  = $file->getClientOriginalExtension() ?: pathinfo($storedPath, PATHINFO_EXTENSION);
        } elseif ($file instanceof File) {
            $storedPath = Storage::disk($disk)->putFile('media/originals/' . date('Y/m'), $file);
            $mime = $file->getMimeType() ?: Storage::disk($disk)->mimeType($storedPath);
            $ext  = pathinfo($storedPath, PATHINFO_EXTENSION);
        } else { // ścieżka string do istniejącego pliku na dysku aplikacji
            // Jeśli ścieżka jest absolutna – skopiuj na dysk docelowy
            if (is_file($file)) {
                $basename = basename($file);
                $target = 'media/originals/' . date('Y/m') . '/' . $basename;
                Storage::disk($disk)->put($target, file_get_contents($file));
                $storedPath = $target;
            } else {
                // traktuj jako ścieżkę wewnątrz dysku
                $storedPath = (string) $file;
            }
            $mime = Storage::disk($disk)->mimeType($storedPath);
            $ext  = pathinfo($storedPath, PATHINFO_EXTENSION);
        }

        // 2) Metadane oryginału
        $size = Storage::disk($disk)->size($storedPath);
        [$w, $h] = ImageEngine::dimensions($disk, $storedPath);
        $hash = sha1((string) Storage::disk($disk)->get($storedPath));

        // 3) Utwórz rekord MediaAsset
        /** @var MediaAsset $asset */
        $asset = $this->media()->create([
            'id'             => (string) Str::uuid(),
            'collection'     => $collection,
            'disk'           => $disk,
            'original_path'  => $storedPath,
            'original_mime'  => $mime,
            'original_ext'   => $ext,
            'original_size'  => $size,
            'width'          => $w,
            'height'         => $h,
            'hash'           => $hash,
            'meta'           => null,
        ]);

        // 4) Eager generation?
        if (config('media-kit.mode') === 'eager') {
            ImageEngine::generateAllVariants($asset);
        }

        return $asset;
    }

    /**
     * Dodaj plik z URL (pobieranie do tymczasówki, potem zapis jak UploadedFile).
     * Uwaga: bez zewnętrznych żądań w środowiskach zabronionych – tu prosta implementacja file_get_contents.
     */
    public function addMediaFromUrl(string $url, string $collection = 'default'): MediaAsset
    {
        $contents = @file_get_contents($url);
        if ($contents === false) {
            throw new \RuntimeException("Nie można pobrać pliku z URL: {$url}");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mediakit_');
        file_put_contents($tmp, $contents);

        // Spróbuj wywnioskować rozszerzenie z URL
        $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'bin';
        $tmpTarget = $tmp . '.' . $ext;
        @rename($tmp, $tmpTarget);

        $file = new File($tmpTarget);
        try {
            return $this->addMedia($file, $collection);
        } finally {
            @unlink($tmpTarget);
        }
    }

    /**
     * Pierwszy plik z danej kolekcji (np. obraz wyróżniający).
     */
    public function getFirstMedia(string $collection = 'default'): ?MediaAsset
    {
        return $this->media()
            ->where('collection', $collection)
            ->latest('created_at')
            ->first();
    }

    /**
     * Wszystkie pliki z danej kolekcji.
     */
    public function getMedia(string $collection = 'default')
    {
        return $this->media()
            ->where('collection', $collection)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Usuń wszystkie media z kolekcji (rekordy + pliki wariantów).
     * Uwaga: nie kasuje oryginałów fizycznie – na shared hostingu lepiej użyć komendy media:cleanup.
     */
    public function clearMediaCollection(string $collection = 'default'): int
    {
        return (int) $this->media()
            ->where('collection', $collection)
            ->delete();
    }
}
