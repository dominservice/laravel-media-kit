<?php

namespace Dominservice\MediaKit\Support;

use Dominservice\MediaKit\Models\MediaAsset;

/**
 * Pomocnik generujący ścieżki do wariantów i katalogów.
 * Zapewnia strukturę kompatybilną ze wszystkimi systemami plików.
 */
class PathHelper
{
    /**
     * Zwraca relatywną ścieżkę docelową wariantu.
     * np. "media/variants/2025/10/{uuid}__md__abcd1234.webp"
     */
    public static function variantPath(MediaAsset $asset, string $variant, string $format): string
    {
        $hash = substr($asset->hash ?? sha1($asset->id), 0, 12);
        $dir = 'media/variants/' . date('Y/m');
        $file = $asset->id . '__' . $variant . '__' . $hash . '.' . strtolower($format);
        return "{$dir}/{$file}";
    }

    /**
     * Ścieżka do oryginału (np. jeśli trzeba coś odtworzyć z backupu).
     */
    public static function originalPath(MediaAsset $asset): string
    {
        return $asset->original_path;
    }

    /**
     * Ścieżka tymczasowa (np. przy regeneracji wariantów).
     */
    public static function tempPath(string $suffix = ''): string
    {
        $suffix = $suffix ? "_{$suffix}" : '';
        return sys_get_temp_dir() . '/mediakit_tmp_' . uniqid() . $suffix;
    }
}
