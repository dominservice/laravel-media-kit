@props([
  'asset',                         // \Dominservice\MediaKit\Models\MediaAsset lub null
  'variants' => ['sm','md','lg','xl'], // lista wariantów w kolejności rosnącej
  'sizes' => null,                 // np. '(min-width: 1200px) 1200px, (min-width: 768px) 768px, 100vw'
  'alt' => '',
  'class' => '',
])

@php
    $sizesAttr = $sizes ?: config('media-kit.responsive.default_sizes');

    $widthMap = config('media-kit.responsive.widths', []);
    $buildSrcset = function (string $format) use ($asset, $variants, $widthMap) {
        $parts = [];
        foreach ($variants as $v) {
            $w = $widthMap[$v] ?? null;
            if (!$w) continue; // pomiń warianty bez zdefiniowanej szerokości
            $url = route('mediakit.media.show', [$asset->uuid, $v, $asset->uuid . '-' . $v . '.' . $format]);
            $parts[] = $url . ' ' . $w . 'w';
        }
        return implode(', ', $parts);
    };

    $avifSet = $asset ? $buildSrcset('avif') : '';
    $webpSet = $asset ? $buildSrcset('webp') : '';

    // Fallback: użyj środkowego wariantu (np. md) jeśli istnieje, w przeciwnym razie pierwszego
    $fallbackVariant = in_array('md', $variants, true) ? 'md' : ($variants[intval(floor(count($variants)/2))] ?? ($variants[0] ?? 'md'));
    $fallbackUrl = $asset
        ? route('mediakit.media.show', [$asset->uuid, $fallbackVariant, $asset->uuid . '-' . $fallbackVariant . '.jpg'])
        : '';
@endphp

@if($asset)
    <picture>
        @if($avifSet)
            <source type="image/avif" srcset="{{ $avifSet }}" sizes="{{ $sizesAttr }}" />
        @endif
        @if($webpSet)
            <source type="image/webp" srcset="{{ $webpSet }}" sizes="{{ $sizesAttr }}" />
        @endif

        <img
                src="{{ $fallbackUrl }}"
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
