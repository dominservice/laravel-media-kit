<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dysk do zapisu plików
    |--------------------------------------------------------------------------
    |
    | Nazwa dysku zdefiniowanego w config/filesystems.php.
    | Najczęściej będzie to "public" lub "s3".
    |
    */
    'disk' => env('MEDIA_KIT_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Kolejność preferowanych formatów
    |--------------------------------------------------------------------------
    */
    'formats_priority' => ['avif', 'webp', 'jpeg', 'png'],

    /*
    |--------------------------------------------------------------------------
    | Jakość dla poszczególnych formatów
    |--------------------------------------------------------------------------
    */
    'default_quality' => [
        'avif' => 45,
        'webp' => 75,
        'jpeg' => 72,
        'png'  => null, // bezstratnie
    ],

    /*
    |--------------------------------------------------------------------------
    | Czy usuwać metadane (EXIF/IPTC)
    |--------------------------------------------------------------------------
    */
    'strip_metadata' => true,

    /*
    |--------------------------------------------------------------------------
    | Progresywne JPEG
    |--------------------------------------------------------------------------
    */
    'progressive_jpeg' => true,

    /*
    |--------------------------------------------------------------------------
    | Czy powiększać obrazy poniżej rozmiaru docelowego
    |--------------------------------------------------------------------------
    */
    'upscale' => false,

    /*
    |--------------------------------------------------------------------------
    | Tło przy konwersji przezroczystych obrazów do JPEG
    |--------------------------------------------------------------------------
    */
    'background_for_transparent_to_jpeg' => '#ffffff',

    /*
    |--------------------------------------------------------------------------
    | Tryb generacji wariantów
    |--------------------------------------------------------------------------
    |
    | eager – generuje wszystkie warianty natychmiast po uploadzie
    | lazy  – generuje wariant dopiero przy pierwszym żądaniu
    |
    */
    'mode' => env('MEDIA_KIT_MODE', 'eager'),

    /*
    |--------------------------------------------------------------------------
    | Definicje wariantów (rozmiary)
    |--------------------------------------------------------------------------
    */
    'variants' => [
        'thumb' => ['fit' => [320, 320]],
        'sm'    => ['width' => 480],
        'md'    => ['width' => 768],
        'lg'    => ['width' => 1200],
        'xl'    => ['width' => 1600],
        'md@2x' => ['width' => 1536],
        'lg@2x' => ['width' => 2400],
    ],

    /*
    |--------------------------------------------------------------------------
    | Które formaty są włączone dla poszczególnych wariantów
    |--------------------------------------------------------------------------
    */
    'enabled_formats_per_variant' => [
        'thumb' => ['avif', 'webp', 'jpeg'],
        '*'     => ['avif', 'webp', 'jpeg', 'png'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Responsywność (srcset / sizes)
    |--------------------------------------------------------------------------
    */
    'responsive' => [
        'order' => ['sm', 'md', 'lg', 'xl'],
        'widths' => [
            'sm' => 480,
            'md' => 768,
            'lg' => 1200,
            'xl' => 1600,
        ],
        'default_sizes' => '(min-width: 1200px) 1200px, (min-width: 768px) 768px, 100vw',
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN i podpisywanie URL (CloudFront)
    |--------------------------------------------------------------------------
    */
    'cdn' => [
        'base_url' => env('MEDIA_KIT_CDN', ''),
        'signer' => env('MEDIA_KIT_CDN_SIGNER', 'none'), // none | cloudfront
        'cloudfront' => [
            'key_pair_id' => env('CLOUDFRONT_KEY_PAIR_ID'),
            'private_key_path' => env('CLOUDFRONT_PRIVATE_KEY_PATH'),
            'expires' => env('CLOUDFRONT_URL_EXPIRES', 3600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Wideo (lokalne lub zdalne)
    |--------------------------------------------------------------------------
    */
    'video' => [
        'mode' => env('MEDIA_KIT_VIDEO_MODE', 'basic'), // basic | remote
        'remote' => [
            'driver' => env('MEDIA_KIT_VIDEO_DRIVER', 'cloudflare'),
            'cloudflare' => [
                'account_id' => env('CF_STREAM_ACCOUNT_ID'),
                'embed_type' => 'iframe', // iframe | videojs
            ],
        ],
        'poster' => ['variant' => 'md'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nagłówki cache dla wariantów
    |--------------------------------------------------------------------------
    */
    'cache_headers' => [
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ],

    // Reuse/Dedup: ponowne użycie już wygenerowanych wariantów po hash'u oryginału
    'cache' => [
        'enable_reuse' => true,      // jeśli true, przed generacją spróbujemy skopiować wariant z innego assetu o tym samym hash'u
        'only_same_disk' => true,    // czy ograniczyć reuse do tego samego dysku (zalecane na shared hostingu)
    ],

    // Opcjonalne cache aplikacyjne (mem/czasowe, nie wymagane do reuse)
    'cache_store' => [
        'store' => env('MEDIA_KIT_CACHE_STORE', null), // np. 'file', 'redis' lub null (domyślne)
        'ttl'   => env('MEDIA_KIT_CACHE_TTL', 3600),   // sekundy
    ],

    // Presety filtrów obrazu (kolejność ma znaczenie)
    'filters_presets' => [
        // przykładowe presety
        'grayscale' => [
            ['grayscale' => true],
        ],
        'blur_light' => [
            ['blur' => 1], // 1-3 (większa wartość = mocniejszy)
        ],
        'watermark_logo' => [
            [
                'watermark_image' => [
                    // ścieżka do pliku PNG (z przezroczystością) w systemie plików aplikacji
                    'path' => storage_path('app/public/watermarks/logo.png'),
                    // pozycja: top-left, top-right, bottom-left, bottom-right, center
                    'position' => 'bottom-right',
                    // 0..1 przezroczystość (1 = pełny, 0.3 = lekki)
                    'opacity' => 0.35,
                    // skala znaku wodnego względem szerokości obrazu (np. 0.2 = 20%)
                    'scale' => 0.2,
                    // marginesy (px)
                    'offset_x' => 16,
                    'offset_y' => 16,
                ],
            ],
        ],
    ],

    // (OPCJONALNE) — przykład użycia predefiniowanego preset-u w wariantach:
    // 'variants' => [
    //     'thumb' => ['fit' => [320, 320], 'filters' => ['grayscale']],
    //     'md'    => ['width' => 768,      'filters' => ['watermark_logo']],
    // ],

];
