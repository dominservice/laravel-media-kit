<?php

namespace Dominservice\MediaKit\Services;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Models\MediaVariant;
use Dominservice\MediaKit\Support\PathHelper;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reuse wariantów między assetami o identycznym hash'u oryginału.
 * Unikamy ponownego generowania, jeśli ktoś wcześniej przeliczył to samo.
 */
class VariantReuse
{
    /**
     * Spróbuj odnaleźć istniejący wariant (na podstawie hash oryginału) i skopiować go dla bieżącego assetu.
     * Zwraca MediaVariant lub null, jeśli nie znaleziono.
     */
    public static function tryReuse(MediaAsset $asset, string $name, string $format): ?MediaVariant
    {
        if (!Config::get('media-kit.cache.enable_reuse', true)) {
            return null;
        }

        $hash = (string) $asset->hash;
        if ($hash === '') {
            return null;
        }

        // Ogranicz do tego samego dysku? (zwykle tak — różne dyski mogą być innymi storage'ami)
        $sameDisk = (bool) Config::get('media-kit.cache.only_same_disk', true);
        $diskCond = $sameDisk ? "AND ma.disk = ?" : "";

        // Znajdź DOWOLNY inny asset z tym samym hashem, który ma już wariant {name, format}
        $bindings = [$hash, $name, $format];
        $sql = "
            SELECT mv.*, ma.disk
            FROM media_assets ma
            JOIN media_variants mv ON mv.asset_uuid = ma.uuid
            WHERE ma.hash = ?
              AND mv.name = ?
              AND mv.format = ?
              $diskCond
            ORDER BY mv.id DESC
            LIMIT 1
        ";

        if ($sameDisk) {
            $bindings[] = $asset->disk;
        }

        $row = DB::selectOne($sql, $bindings);
        if (!$row) {
            return null;
        }

        $srcDisk = (string) $row->disk;
        $srcPath = (string) $row->path;

        $dstDisk = $asset->disk;
        $dstRel  = PathHelper::variantPath($asset, $name, $format);

        // Fizyczne skopiowanie pliku
        $src = Storage::disk($srcDisk)->path($srcPath);
        $dst = Storage::disk($dstDisk)->path($dstRel);
        @mkdir(dirname($dst), 0775, true);

        if (!is_file($src)) {
            return null; // plik źródłowy nie istnieje
        }

        if (!@copy($src, $dst)) {
            return null; // nie udało się skopiować
        }

        // Zbierz metadane i zapisz MediaVariant
        [$w, $h] = @getimagesize($dst) ?: [null, null];
        $size = @filesize($dst) ?: null;

        return $asset->variants()->create([
            'name'         => $name,
            'format'       => $format,
            'disk'         => $dstDisk,
            'path'         => $dstRel,
            'width'        => $w ? (int)$w : null,
            'height'       => $h ? (int)$h : null,
            'quality'      => config("media-kit.default_quality.$format"),
            'size'         => $size,
            'generated_at' => now(),
            'meta'         => ['reused' => true],
        ]);
    }
}
