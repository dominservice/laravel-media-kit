@php
    /**
     * Komponent "kind video"
     *
     * Użycie (tryb basic, bez transkodera):
     *   <x-media-kind-video :model="$post" kind="video_avatar" rendition="hd" title="..." />
     *
     * Użycie (tryb remote/Cloudflare) – jeśli w meta assetu zapiszesz 'video_uid':
     *   <x-media-kind-video :model="$post" kind="video_avatar" title="..." />
     *
     * Zasada:
     *  - Szuka assetu w kolekcji zdefiniowanej przez kind (domyślnie "video").
     *  - W trybie "basic": czyta ścieżkę z $asset->meta['video_renditions'][rendition] i renderuje <video>.
     *  - W trybie "remote": jeżeli w meta jest 'video_uid' – renderuje iframe Cloudflare (VideoManager).
     *  - Poster: jeśli dla kind jest 'poster_kind', można przekazać 'posterVariant' aby pobrać obraz.
     */
@endphp

@props([
  'model',                    // Eloquent model z relacją media() – wymagany
  'kind' => 'video_avatar',   // nazwa kind z configu
  'rendition' => 'hd',        // hd|sd|mobile — dla basic
  'title' => '',
  'width' => '100%',
  'height' => 'auto',
  'posterVariant' => null,    // np. 'md' – jeśli chcesz wymusić wariant postera
])

@php
    use Dominservice\MediaKit\Support\Kinds\KindRegistry;

    /** @var \Illuminate\Database\Eloquent\Model $model */
    $cfg = KindRegistry::get($kind) ?? [];
    $collection = $cfg['collection'] ?? 'video';
    $posterKind = $cfg['poster_kind'] ?? null;

    /** @var \Dominservice\MediaKit\Models\MediaAsset|null $asset */
    $asset = $model->media()->where('collection', $collection)->latest()->first();
@endphp

@if($asset)
    @php
        $mode = config('media-kit.video.mode', 'basic');

        // Poster URL (jeśli zdefiniowany poster_kind i istnieje asset)
        $posterUrl = null;
        if ($posterKind) {
            $posterCollection = KindRegistry::collectionFor($posterKind, $posterKind);
            $posterAsset = $model->media()->where('collection', $posterCollection)->latest()->first();
            if ($posterAsset) {
                $pv = $posterVariant ?: (KindRegistry::displayVariant($posterKind, 'md'));
                $posterUrl = route('mediakit.media.show', [$posterAsset->id, $pv, $posterAsset->id.'-'.$pv.'.jpg']);
            }
        }

        if ($mode !== 'remote') {
            // BASIC: czytamy rendition z meta
            $meta = (array) ($asset->meta ?? []);
            $rends = (array) ($meta['video_renditions'] ?? []);
            $path = $rends[$rendition] ?? null;

            if ($path) {
                $src = \Storage::disk($asset->disk)->url($path);
                // render <video>
                echo '<video controls preload="metadata" style="width:'.e($width).';height:'.e($height).'" title="'.e($title).'"';
                if ($posterUrl) {
                    echo ' poster="'.e($posterUrl).'"';
                }
                echo '>';
                echo '<source src="'.e($src).'" type="video/mp4">';
                echo '</video>';
            }
        } else {
            // REMOTE: spodziewamy się meta['video_uid']
            $meta = (array) ($asset->meta ?? []);
            $uid  = (string) ($meta['video_uid'] ?? '');

            if ($uid !== '') {
                // użyj VideoManager do generowania HTML (Cloudflare)
                echo \Dominservice\MediaKit\Services\Video\VideoManager::embedHtml($uid, [
                    'title' => $title,
                    'width' => $width,
                    'height'=> $height,
                ]);
            }
        }
    @endphp
@endif
