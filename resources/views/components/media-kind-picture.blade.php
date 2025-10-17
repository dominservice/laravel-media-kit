@php
    /**
     * Komponent "kind picture"
     *
     * Użycie:
     *   <x-media-kind-picture :model="$post" kind="avatar" alt="..." class="..." />
     *   <x-media-kind-picture :model="$post" kind="gallery" variant="lg" />
     *
     * Zasada:
     *  - Wyszukuje pierwszy asset w kolekcji wynikającej z kind (config('media-kit.kinds.{kind}.collection')).
     *  - Jeśli nie podasz "variant", użyje domyślnego display z kind (config('...display')).
     *  - Renderuje <picture> z AVIF/WebP/JPEG jak x-media-picture.
     */
@endphp

@props([
  'model',                 // Eloquent model z relacją media() (HasMedia) – wymagany
  'kind' => 'avatar',      // nazwa kind z configu
  'variant' => null,       // konkretny wariant; gdy null -> użyje display z kind
  'alt' => '',
  'class' => '',
  'sizes' => null,         // możesz podać, ale tu używamy pojedynczego wariantu (nie srcset)
])

@php
    /** @var \Illuminate\Database\Eloquent\Model $model */
    $cfg = \Dominservice\MediaKit\Support\Kinds\KindRegistry::get($kind) ?? [];
    $collection = $cfg['collection'] ?? $kind;
    $display = $variant ?: ($cfg['display'] ?? 'md');

    $asset = $model->media()->where('collection', $collection)->latest()->first();
@endphp

@if($asset)
    <picture>
        <source type="image/avif" srcset="{{ route('mediakit.media.show', [$asset->id, $display, $asset->id.'-'.$display.'.avif']) }}" />
        <source type="image/webp" srcset="{{ route('mediakit.media.show', [$asset->id, $display, $asset->id.'-'.$display.'.webp']) }}" />
        <img
                src="{{ route('mediakit.media.show', [$asset->id, $display, $asset->id.'-'.$display.'.jpg']) }}"
                alt="{{ $alt }}"
                class="{{ $class }}"
                loading="lazy"
                decoding="async"
                @if($asset->width && $asset->height)
                    width="{{ $asset->width }}" height="{{ $asset->height }}"
                @endif
        >
    </picture>
@endif
