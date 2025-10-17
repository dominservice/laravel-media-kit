@props([
  'asset',           // \Dominservice\MediaKit\Models\MediaAsset lub null
  'variant' => 'md', // nazwa wariantu z config('media-kit.variants')
  'alt' => '',
  'class' => '',
])

@if($asset)
    <picture>
        {{-- preferowane źródła nowoczesne --}}
        <source
                type="image/avif"
                srcset="{{ route('mediakit.media.show', [$asset->uuid, $variant, $asset->uuid.'-'.$variant.'.avif']) }}"
        />
        <source
                type="image/webp"
                srcset="{{ route('mediakit.media.show', [$asset->uuid, $variant, $asset->uuid.'-'.$variant.'.webp']) }}"
        />

        {{-- fallback JPEG (lub PNG jeśli wolisz) --}}
        <img
                src="{{ route('mediakit.media.show', [$asset->uuid, $variant, $asset->uuid.'-'.$variant.'.jpg']) }}"
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
