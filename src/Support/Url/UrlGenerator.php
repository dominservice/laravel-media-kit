<?php

namespace Dominservice\MediaKit\Support\Url;

class UrlGenerator
{
    /**
     * Buduje absolutny URL do zasobu multimedialnego.
     * Jeśli w configu ustawiono CDN (base_url), użyje go.
     * Opcjonalnie podpisze URL (np. CloudFront).
     */
    public static function make(string $path): string
    {
        $path = ltrim($path, '/');

        $base = rtrim((string) config('media-kit.cdn.base_url', ''), '/');
        $url  = $base !== '' ? $base . '/' . $path : url($path);

        $signer = (string) config('media-kit.cdn.signer', 'none');

        return match ($signer) {
            'cloudfront' => CloudFrontSigner::sign($url),
            default      => $url,
        };
    }
}
