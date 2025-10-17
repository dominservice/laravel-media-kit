# Dominservice Laravel Media Kit

**A modern, modular, shared-hosting–friendly media management toolkit for Laravel 9–12.**  
Process, convert, and deliver images & videos (AVIF, WebP, JPEG, MP4, Cloudflare) with lazy/eager generation, responsive variants, and domain‑level `Kinds`.

---

## 🚀 Features

- ✅ Supports **Laravel 9–12**, PHP ≥ 8.1
- 📷 Convert images to **AVIF / WebP / JPEG / PNG**
- 🧩 Configurable **variants** (thumb, sm, md, lg, xl, 2x)
- 🌐 **Kinds layer** – domain‑specific image sets (avatar, gallery, video poster…)
- 🧠 Smart deduplication (hash‑based)
- ⚙️ Lazy / eager variant generation
- 🪶 Strip EXIF/IPTC metadata and generate progressive JPEGs
- 🪄 Built‑in filters (`grayscale`, `blur`, `watermark`)
- 🎬 **Video support** – *basic* (local renditions) and *remote* (Cloudflare / Bunny / Cloudinary)
- ☁️ **CDN support** with CloudFront signed URLs
- 🧰 **MediaUploader** API with replace/keep/delete policies
- 🔄 CLI tools for regeneration, cleanup, diagnostics
- 🧪 Fully tested (Orchestra Testbench)
- 🤝 Open for contributions — [Buy me a coffee ☕](https://ko-fi.com/dominservice)

---

## 📦 Installation

```bash
composer require dominservice/laravel-media-kit
php artisan vendor:publish --provider="Dominservice\MediaKit\MediaKitServiceProvider" --tag=mediakit-config
php artisan migrate
```

> Works out of the box on shared hosting – no queue or external binaries required.  
> Optional: `ext-gd`, `ext-imagick`, or `rosell-dk/webp-convert` for WebP fallback.

---

## ⚙️ Configuration

### Variants and Formats

```php
'formats_priority' => ['avif','webp','jpeg','png'],
'variants' => [
  'thumb' => ['fit' => [320,320]],
  'sm'    => ['width' => 480],
  'md'    => ['width' => 768],
  'lg'    => ['width' => 1200],
  'xl'    => ['width' => 1600],
],
'enabled_formats_per_variant' => [
  'thumb' => ['avif','webp','jpeg'],
  '*'     => ['avif','webp','jpeg','png'],
],
```

### Kinds (Domain‑level definitions)

```php
'kinds' => [
  'avatar' => [
    'collection' => 'avatar',
    'disk'       => env('MEDIA_KIT_DISK_AVATAR', 'public'),
    'display'    => 'lg',
    'variants'   => ['thumb','sm','md','lg'],
    'aliases'    => ['photo','featured'],
  ],
  'gallery' => [
    'collection' => 'gallery',
    'display'    => 'md',
    'variants'   => ['sm','md','lg','xl'],
  ],
  'video_avatar' => [
    'collection' => 'video',
    'renditions' => ['hd','sd','mobile'],
    'poster_kind'=> 'video_poster',
  ],
  'video_poster' => [
    'collection' => 'video_poster',
    'display'    => 'lg',
  ],
],
```

### CDN Support

```php
'cdn' => [
  'base_url' => env('MEDIA_KIT_CDN', ''),
  'signer'   => env('MEDIA_KIT_CDN_SIGNER', 'none'),  // none|cloudfront
  'cloudfront' => [
    'key_pair_id'      => env('CLOUDFRONT_KEY_PAIR_ID'),
    'private_key_path' => env('CLOUDFRONT_PRIVATE_KEY_PATH'),
    'expires'          => env('CLOUDFRONT_URL_EXPIRES', 3600),
  ],
],
```

### Video Configuration

```php
'video' => [
  'mode' => env('MEDIA_KIT_VIDEO_MODE', 'basic'), // basic|remote
  'remote' => [
    'driver' => env('MEDIA_KIT_VIDEO_DRIVER', 'cloudflare'),
    'cloudflare' => [
      'account_id' => env('CF_STREAM_ACCOUNT_ID'),
      'embed_type' => 'iframe', // iframe|videojs
    ],
  ],
  'basic_renditions' => ['hd','sd','mobile'],
  'poster' => ['variant' => 'md'],
],
```

---

## 🧩 Usage

### In Models

```php
use Dominservice\MediaKit\Traits\HasMedia;
use Dominservice\MediaKit\Traits\HasMediaKinds;

class Post extends Model
{
    use HasMedia, HasMediaKinds;
}
```

### Uploading Files

```php
// simple upload
$post->addMedia($request->file('cover'), 'featured');

// domain-specific upload (via MediaUploader)
use Dominservice\MediaKit\Services\MediaUploader;

MediaUploader::uploadImage($post, 'avatar', $request->file('avatar'), 'replace', ['grayscale']);
```

### Accessing Media

```php
$asset = $post->getFirstMedia('featured');
$url   = route('mediakit.media.show', [$asset->id, 'lg']);
```

Or via Kind helpers:

```php
$post->avatarUrl();           // default display
$post->avatarUrl('md');       // specific variant
$post->videoUrl('sd');        // basic video rendition
$post->videoPosterUrl('lg');  // poster for video kind
```

---

## 🎨 Blade Components

### Image Components

```bladehtml
<x-media-picture :asset="$post->getFirstMedia('featured')" alt="Preview" />
<x-media-responsive :asset="$post->getFirstMedia('featured')" :variants="['sm','md','lg']" />
```

### Kind-based Image Components

```bladehtml
<x-media-kind-picture :model="$post" kind="avatar" alt="Author" class="rounded-full" />
<x-media-kind-picture :model="$post" kind="gallery" variant="lg" class="w-full" />
```

### Video Components

#### Basic (local renditions)

```bladehtml
<x-media-kind-video :model="$post" kind="video_avatar" rendition="hd" title="Trailer" />
```

#### Remote (e.g. Cloudflare Stream)

```bladehtml
<x-media-kind-video :model="$post" kind="video_avatar" title="Promo Video" />
```

---

## 🪄 Filters

You can apply transformations on upload:

```php
MediaUploader::uploadImage($post, 'avatar', $request->file('avatar'), 'replace', ['grayscale']);
MediaUploader::uploadImage($post, 'avatar', $request->file('avatar'), 'replace', [['blur' => 2]]);
MediaUploader::uploadImage($post, 'avatar', $request->file('avatar'), 'replace', [['watermark' => ['path'=>'logo.png']]]);
```

Each filter is configurable and can be extended by registering new filter handlers.

---

## ⚙️ Lazy vs Eager Mode

- **Eager:** Variants are generated immediately upon upload (default).  
- **Lazy:** Variants are generated on first access via the `/media/{id}/{variant}` route.

Set in `.env`:

```
MEDIA_KIT_MODE=eager
```

---

## 🧠 Architecture Overview

```
┌────────────────────────────┐
│ MediaAsset (Eloquent)      │
│   ↳ hasMany → MediaVariant │
│   ↳ belongsTo Morph Model  │
└────────────────────────────┘
          │
          ▼
┌────────────────────────────┐
│ ImageEngine                │
│  - Resize / convert        │
│  - Fallback (WebPConvert)  │
│  - Metadata stripping      │
└────────────────────────────┘
          │
          ▼
┌────────────────────────────┐
│ MediaUploader              │
│  - Policies (replace...)   │
│  - Filters pipeline        │
└────────────────────────────┘
          │
          ▼
┌────────────────────────────┐
│ Blade Components            │
│  - x-media-picture          │
│  - x-media-kind-picture     │
│  - x-media-kind-video       │
└────────────────────────────┘
```

---

## 🧰 CLI Commands

```bash
php artisan media:diagnose           # Check GD/Imagick/WebP/AVIF availability
php artisan media:regenerate         # Regenerate all variants
php artisan media:regenerate --only-missing
php artisan media:cleanup            # Remove orphaned variants
php artisan media:cleanup --dry-run
```

---

## 🧪 Testing

```bash
composer install
composer test
# or
vendor/bin/phpunit
```

The test suite uses Orchestra Testbench.  
AVIF/WebP are mocked unless available natively.

---

## 🛠️ Extending

You can extend the package by publishing config and adding custom filters, variant rules, or Kind definitions.

```bash
php artisan vendor:publish --provider="Dominservice\MediaKit\MediaKitServiceProvider" --tag=mediakit-config
```

---

## 🤝 Contributing

Pull requests are welcome!  
If you want to improve the package, feel free to fork and submit PRs.  
Please ensure all tests pass (`composer test`).

---

## ☕ Support

If this package helps you, consider supporting future development:  
👉 [Buy me a coffee on Ko‑fi](https://ko-fi.com/dominservice)

---

## 📄 License

Licensed under the MIT License.  
© Dominservice, 2025
