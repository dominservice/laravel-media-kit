<?php

namespace Dominservice\MediaKit\Support\Kinds;

use Illuminate\Support\Arr;

class KindRegistry
{
    public static function all(): array
    {
        return (array) config('media-kit.kinds', []);
    }

    public static function get(string $kind): ?array
    {
        $kinds = self::all();
        if (isset($kinds[$kind])) return $kinds[$kind];

        // aliasy
        foreach ($kinds as $name => $cfg) {
            $aliases = (array) ($cfg['aliases'] ?? []);
            if (in_array($kind, $aliases, true)) {
                return $cfg + ['_resolved_alias' => $kind, '_canonical' => $name];
            }
        }
        return null;
    }

    public static function canonicalName(string $kind): ?string
    {
        $kinds = self::all();
        if (isset($kinds[$kind])) return $kind;

        foreach ($kinds as $name => $cfg) {
            if (in_array($kind, (array) ($cfg['aliases'] ?? []), true)) {
                return $name;
            }
        }
        return null;
    }

    public static function collectionFor(string $kind, ?string $fallback = null): ?string
    {
        $cfg = self::get($kind);
        return $cfg['collection'] ?? $fallback;
    }

    public static function diskFor(string $kind, ?string $fallback = null): ?string
    {
        $cfg = self::get($kind);
        return $cfg['disk'] ?? $fallback ?? config('media-kit.disk');
    }

    public static function displayVariant(string $kind, ?string $fallback = null): ?string
    {
        $cfg = self::get($kind);
        return $cfg['display'] ?? $fallback ?? 'md';
    }

    public static function allowedVariants(string $kind): array
    {
        $cfg = self::get($kind);
        return (array) ($cfg['variants'] ?? array_keys((array) config('media-kit.variants', [])));
    }

    public static function renditions(string $kind): array
    {
        $cfg = self::get($kind);
        return (array) ($cfg['renditions'] ?? []);
    }

    public static function posterKind(string $kind): ?string
    {
        $cfg = self::get($kind);
        return $cfg['poster_kind'] ?? null;
    }
}
