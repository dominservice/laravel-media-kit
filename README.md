

# dominservice/laravel-media-kit

**Lekki, modularny i wydajny system zarządzania multimediami dla Laravel 9–12**  
(shared hosting ready – działa bez kolejek i z minimalnymi zależnościami)

---

## 🚀 Funkcje

- 🔄 Automatyczna konwersja obrazów do **AVIF / WebP / JPEG / PNG**
- 🧩 Warianty i rozdzielczości (`thumb`, `sm`, `md`, `lg`, `xl`, `@2x`)
- 🖼️ Gotowe komponenty Blade: `<x-media-picture>` i `<x-media-responsive>`
- ⚙️ Tryb **eager** (generacja przy uploadzie) lub **lazy** (pierwsze żądanie)
- ☁️ Wsparcie dla **CDN / CloudFront** z podpisywaniem URL-i
- 🎬 Obsługa **wideo lokalnych** oraz **Cloudflare Stream**
- 🧰 Zintegrowane komendy CLI (`media:diagnose`, `media:cleanup`, `media:regenerate`)
- 💾 Bezpieczne UUID i polimorficzne relacje (`HasMedia`)
- 🧠 Zero zależności od zewnętrznych procesów – pełna zgodność z hostingiem współdzielonym

---

## ⚙️ Instalacja

```bash
composer require dominservice/laravel-media-kit
php artisan vendor:publish --provider="Dominservice\\MediaKit\\MediaKitServiceProvider" --tag=mediakit-config
php artisan migrate
```
 📌 Wymagania: PHP ≥ 8.1, Laravel 9–12, GD lub Imagick (opcjonalnie rosell-dk/webp-convert jako fallback).

___

## 🧩 Użycie
### 1️⃣ Dodaj trait do swojego modelu
```php
$asset = $post->addMedia($request->file('image'), 'featured');
```
### 3️⃣ Wyświetl w widoku
__Obrazek podstawowy:__
```bladehtml
<x-media-picture :asset="$post->getFirstMedia('featured')" alt="Miniatura wpisu" class="rounded shadow" />
```
__Obrazek responsywny:__
```bladehtml
<x-media-responsive 
    :asset="$post->getFirstMedia('featured')" 
    :variants="['sm','md','lg','xl']" 
    sizes="(min-width: 1200px) 1200px, (min-width: 768px) 768px, 100vw"
    class="w-full rounded"
/>
```
__Wideo:__
```bladehtml
<x-media-video src="{{ Storage::url('videos/promo.mp4') }}" title="Prezentacja" />
```
__Cloudflare Stream:__
```bladehtml
<x-media-video uid="c5ffabcdf1b2b3x9y" title="Spot reklamowy" />
```
___

## 🌐 Trasy
```bash
GET /media/{asset-uuid}/{variant}/{filename?}
```
Przykład:
`/media/abc12345-md/test.webp`

* W trybie eager zwraca gotowy wariant.
* W trybie lazy wygeneruje brakujący wariant przy pierwszym wywołaniu.

___

## ⚡ Komendy Artisan
| Komenda                                                             | Opis                                          |
|---------------------------------------------------------------------|-----------------------------------------------|
| `php artisan media:diagnose`                                          | Sprawdza środowisko (GD, Imagick, WebP, AVIF) |
| `php artisan media:regenerate`| Regeneruje wszystkie warianty                 |
| `php artisan media:regenerate --only-missing`| Tylko brakujące warianty                      |
| `php artisan media:cleanup`| Usuwa osierocone pliki wariantów              |
| `php artisan media:cleanup --dry-run`| Pokazuje, co byłoby usunięte                  |

___

## ⚙️ Konfiguracja (`config/media-kit.php`)
Najważniejsze klucze:
```php
'disk' => 'public',                 // lub 's3'
'mode' => 'eager',                  // eager | lazy
'formats_priority' => ['avif','webp','jpeg','png'],

'variants' => [
    'thumb' => ['fit' => [320,320]],
    'sm'    => ['width' => 480],
    'md'    => ['width' => 768],
    'lg'    => ['width' => 1200],
    'xl'    => ['width' => 1600],
],

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
```
___

## ☁️ CDN i CloudFront
Plik `.env`:
```dotenv
MEDIA_KIT_CDN=https://cdn.example.com
MEDIA_KIT_CDN_SIGNER=cloudfront
CLOUDFRONT_KEY_PAIR_ID=APKA123EXAMPLE
CLOUDFRONT_PRIVATE_KEY_PATH=/path/to/private_key.pem
CLOUDFRONT_URL_EXPIRES=3600
```
Każdy link do wariantu zostanie podpisany przez `CloudFrontSigner`.

___

## 🎬 Wideo (lokalne / Cloudflare Stream)
__Tryb `basic` (lokalne MP4)__
```bladehtml
<x-media-video src="{{ Storage::url('videos/demo.mp4') }}" title="Demo" />
```
__Tryb `remote` (Cloudflare Stream)__
```dotenv
MEDIA_KIT_VIDEO_MODE=remote
MEDIA_KIT_VIDEO_DRIVER=cloudflare
CF_STREAM_ACCOUNT_ID=your_account_id
```
```bladehtml
<x-media-video uid="your-cloudflare-video-uid" title="Prezentacja produktu" />
```
___

## 🧪 Diagnostyka
```bash
php artisan media:diagnose
```
Przykładowy wynik:
```yaml
gd              : OK
imagick         : NIE
imagewebp()     : OK
imageavif()     : NIE
webp-convert    : OK

disk: public
mode: eager
formats_priority: avif,webp,jpeg,png
variants: thumb,sm,md,lg,xl
```
___

## 🧰 Przykład integracji w CMS
```php
// W kontrolerze
$post = Post::find(1);
$post->addMedia($request->file('image'), 'gallery');

// W widoku
@foreach($post->getMedia('gallery') as $image)
    <x-media-picture :asset="$image" variant="md" class="rounded-md" />
@endforeach
```
___

## 📦 Pakiet w skrócie

| Element                                               | Opis                |
| ----------------------------------------------------- | ------------------- |
| `HasMedia`                                            | trait dla modeli    |
| `ImageEngine`                                         | generacja wariantów |
| `MediaAsset` / `MediaVariant`                         | modele Eloquent     |
| `MediaController`                                     | serwowanie plików   |
| `media:cleanup`, `media:regenerate`, `media:diagnose` | CLI                 |
| `UrlGenerator`, `CloudFrontSigner`                    | obsługa CDN         |

___

## 📄 Licencja

MIT © Dominservice

Autor: Mateusz Domin

Repozytorium: github.com/dominservice/laravel-media-kit