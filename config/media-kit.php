<?php

return [

    // Dysk domyślny (można nadpisać per-kind)
    'disk' => env('MEDIA_KIT_DISK', 'public'),

    // Kolejność preferowanych formatów
    'formats_priority' => ['avif', 'webp', 'jpeg', 'png'],

    // Jakości domyślne
    'default_quality' => [
        'avif' => 45,
        'webp' => 75,
        'jpeg' => 72,
        'png'  => null,
    ],

    // Usuwanie EXIF/IPTC
    'strip_metadata' => true,

    // Progresywny JPEG
    'progressive_jpeg' => true,

    // Nie powiększaj obrazów poniżej docelowego rozmiaru
    'upscale' => false,

    // Tło przy konwersji przezroczystości do JPEG
    'background_for_transparent_to_jpeg' => '#ffffff',

    // Tryb generacji wariantów: eager | lazy
    'mode' => env('MEDIA_KIT_MODE', 'eager'),

    // Definicje wariantów (rozmiary)
    'variants' => [
        'thumb' => ['fit' => [320, 320]],
        'sm'    => ['width' => 480],
        'md'    => ['width' => 768],
        'lg'    => ['width' => 1200],
        'xl'    => ['width' => 1600],
        'md@2x' => ['width' => 1536],
        'lg@2x' => ['width' => 2400],
    ],

    // Włączone formaty dla wariantów
    'enabled_formats_per_variant' => [
        'thumb' => ['avif', 'webp', 'jpeg'],
        '*'     => ['avif', 'webp', 'jpeg', 'png'],
    ],

    // Responsywność (srcset/sizes)
    'responsive' => [
        'order' => ['sm','md','lg','xl'],
        'widths' => [
            'sm' => 480,
            'md' => 768,
            'lg' => 1200,
            'xl' => 1600,
        ],
        'default_sizes' => '(min-width: 1200px) 1200px, (min-width: 768px) 768px, 100vw',
    ],

    // CDN + podpisywanie
    'cdn' => [
        'base_url' => env('MEDIA_KIT_CDN', ''),
        'signer' => env('MEDIA_KIT_CDN_SIGNER', 'none'), // none | cloudfront
        'cloudfront' => [
            'key_pair_id' => env('CLOUDFRONT_KEY_PAIR_ID'),
            'private_key_path' => env('CLOUDFRONT_PRIVATE_KEY_PATH'),
            'expires' => env('CLOUDFRONT_URL_EXPIRES', 3600),
        ],
    ],

    // Wideo (lokalne lub zdalne)
    'video' => [
        'mode' => env('MEDIA_KIT_VIDEO_MODE', 'basic'), // basic | remote
        'remote' => [
            'driver' => env('MEDIA_KIT_VIDEO_DRIVER', 'cloudflare'),
            'cloudflare' => [
                'account_id' => env('CF_STREAM_ACCOUNT_ID'),
                'embed_type' => 'iframe', // iframe | videojs
            ],
        ],
        // w trybie basic możesz trzymać renditions (hd/sd/mobile) w meta assetu
        'basic_renditions' => ['hd', 'sd', 'mobile'],
        'poster' => ['variant' => 'md'],
    ],

    // Nagłówki cache
    'cache_headers' => [
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ],

    // Reuse/Dedup: kopiowanie wariantów po hash'u
    'cache' => [
        'enable_reuse' => true,
        'only_same_disk' => true,
    ],

    // Cache aplikacyjny (opcjonalny)
    'cache_store' => [
        'store' => env('MEDIA_KIT_CACHE_STORE', null), // np. 'file', 'redis' lub null
        'ttl'   => env('MEDIA_KIT_CACHE_TTL', 3600),   // sekundy
    ],

    // Presety filtrów (przykłady)
    'filters_presets' => [
        'grayscale' => [
            ['grayscale' => true],
        ],
        'blur_light' => [
            ['blur' => 1],
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

    // **NOWOŚĆ**: Warstwa "Kinds" (aliasy, domyślny display, per-kind dysk, dozwolone warianty)
    'kinds' => [
        'avatar' => [
            'collection' => 'avatar',
            'disk'       => env('MEDIA_KIT_DISK_AVATAR', env('MEDIA_KIT_DISK', 'public')),
            'display'    => 'lg',
            'variants'   => ['thumb','sm','md','lg','xl'],
            'aliases'    => ['photo','featured'],
        ],
        'gallery' => [
            'collection' => 'gallery',
            'disk'       => env('MEDIA_KIT_DISK_GALLERY', env('MEDIA_KIT_DISK', 'public')),
            'display'    => 'md',
            'variants'   => ['sm','md','lg','xl'],
            'aliases'    => [],
        ],
        'video_avatar' => [
            'collection' => 'video',
            'disk'       => env('MEDIA_KIT_DISK_VIDEO', env('MEDIA_KIT_DISK', 'public')),
            // renditions tylko dla trybu 'basic'
            'renditions' => ['hd','sd','mobile'],
            'poster_kind'=> 'video_poster',
            'aliases'    => ['video_main'],
        ],
        'video_poster' => [
            'collection' => 'video_poster',
            'disk'       => env('MEDIA_KIT_DISK_VIDEO_POSTER', env('MEDIA_KIT_DISK', 'public')),
            'display'    => 'lg',
            'variants'   => ['thumb','sm','md','lg'],
            'aliases'    => ['video_cover'],
        ],
    ],
];
