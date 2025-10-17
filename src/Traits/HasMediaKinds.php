<?php

namespace Dominservice\MediaKit\Traits;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Support\Kinds\KindRegistry;
use Illuminate\Support\Facades\Cache;

trait HasMediaKinds
{
    /**
     * URL do obrazu wg kind (z domyślnym display) lub wariantu.
     */
    public function mediaKindUrl(string $kind, ?string $variant = null): ?string
    {
        $canonical = KindRegistry::canonicalName($kind) ?? $kind;
        $collection = KindRegistry::collectionFor($canonical, $canonical);
        $display = $variant ?: KindRegistry::displayVariant($canonical, 'md');

        $asset = $this->media()->where('collection', $collection)->latest()->first();
        if (!$asset) return null;

        // budujemy route do kontrolera mediakit
        return route('mediakit.media.show', [$asset->uuid, $display, $asset->uuid.'-'.$display.'.jpg']);
    }

    public function avatarUrl(?string $variant = null): ?string
    {
        return $this->mediaKindUrl('avatar', $variant);
    }

    public function galleryUrls(?string $variant = null): array
    {
        $collection = KindRegistry::collectionFor('gallery', 'gallery');
        $display = $variant ?: KindRegistry::displayVariant('gallery', 'md');

        $assets = $this->media()->where('collection', $collection)->orderByDesc('created_at')->get();
        return $assets->map(function (MediaAsset $asset) use ($display) {
            return route('mediakit.media.show', [$asset->uuid, $display, $asset->uuid.'-'.$display.'.jpg']);
        })->all();
    }

    /**
     * URL do wideo (basic: bez transkodowania) — wybór rendition (hd/sd/mobile).
     * Zwraca URL lokalnego pliku albo null.
     */
    public function videoUrl(?string $rendition = 'hd'): ?string
    {
        $collection = KindRegistry::collectionFor('video_avatar', 'video');
        $asset = $this->media()->where('collection', $collection)->latest()->first();
        if (!$asset) return null;

        $rend = $rendition ?: 'hd';
        $meta = (array) ($asset->meta ?? []);
        $rends = (array) ($meta['video_renditions'] ?? []);
        $path = $rends[$rend] ?? null;
        if (!$path) return null;

        return \Storage::disk($asset->disk)->url($path);
    }

    public function videoPosterUrl(?string $variant = null): ?string
    {
        $posterKind = KindRegistry::posterKind('video_avatar') ?: 'video_poster';
        return $this->mediaKindUrl($posterKind, $variant);
    }
}
