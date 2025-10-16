@props([
  'uid' => null,   // dla trybu remote (np. Cloudflare Stream UID)
  'src' => null,   // dla trybu basic (np. URL do .mp4)
  'title' => '',
  'width' => '100%',
  'height' => 'auto',
])

@php
    /** @var string $html */
    $html = \Dominservice\MediaKit\Services\Video\VideoManager::embedHtml($uid ?? '', [
        'src' => $src,
        'title' => $title,
        'width' => $width,
        'height' => $height,
    ]);
@endphp

{!! $html !!}
