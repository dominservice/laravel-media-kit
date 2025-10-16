<?php

namespace Dominservice\MediaKit\Services\Video;

use Illuminate\Support\Facades\Config;

class VideoManager
{
    /**
     * Zwraca gotowy HTML do osadzenia wideo.
     * - mode=basic  -> <video controls src="...">
     * - mode=remote -> driver (domyślnie Cloudflare)
     */
    public static function embedHtml(string $uid, array $attrs = []): string
    {
        $mode = (string) Config::get('media-kit.video.mode', 'basic');

        if ($mode !== 'remote') {
            // BASIC: zwykły tag <video>
            $src   = (string) ($attrs['src'] ?? '');
            $title = (string) ($attrs['title'] ?? '');
            $w     = (string) ($attrs['width'] ?? '100%');
            $h     = (string) ($attrs['height'] ?? 'auto');

            if ($src === '') {
                return '<!-- media-video: brak src w trybie basic -->';
            }

            return '<video controls preload="metadata" style="width:' . e($w) . ';height:' . e($h) . '" title="' . e($title) . '">'
                . '<source src="' . e($src) . '" type="video/mp4">'
                . '</video>';
        }

        // REMOTE: wybierz driver (Cloudflare)
        $driver = (string) Config::get('media-kit.video.remote.driver', 'cloudflare');

        return match ($driver) {
            'cloudflare' => Drivers\CloudflareStream::embed($uid, $attrs),
            default      => '<!-- media-video: nieznany driver -->',
        };
    }
}
