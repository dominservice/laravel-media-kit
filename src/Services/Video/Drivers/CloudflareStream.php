<?php

namespace Dominservice\MediaKit\Services\Video\Drivers;

use Illuminate\Support\Facades\Config;

class CloudflareStream
{
    /**
     * Generuje embed Cloudflare Stream.
     * - iframe: https://iframe.videodelivery.net/{UID}
     * - videojs/HLS: https://videodelivery.net/{UID}/manifest/video.m3u8
     */
    public static function embed(string $uid, array $attrs = []): string
    {
        if ($uid === '') {
            return '<!-- media-video: brak UID dla Cloudflare Stream -->';
        }

        $type  = (string) Config::get('media-kit.video.remote.cloudflare.embed_type', 'iframe');
        $title = (string) ($attrs['title'] ?? '');
        $w     = (string) ($attrs['width'] ?? '100%');
        $h     = (string) ($attrs['height'] ?? 'auto');

        if ($type === 'iframe') {
            $src = "https://iframe.videodelivery.net/" . rawurlencode($uid);
            return '<iframe src="' . e($src) . '"'
                . ' style="aspect-ratio:16/9;width:' . e($w) . ';height:' . e($h) . '"'
                . ' allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture"'
                . ' allowfullscreen'
                . ' title="' . e($title) . '"></iframe>';
        }

        // HLS manifest (np. dla odtwarzaczy wspierających HLS)
        $src = "https://videodelivery.net/" . rawurlencode($uid) . "/manifest/video.m3u8";
        return '<video controls preload="metadata" style="width:' . e($w) . ';height:' . e($h) . '" title="' . e($title) . '">'
            . '<source src="' . e($src) . '" type="application/vnd.apple.mpegurl">'
            . '</video>';
    }
}
