<?php

namespace Dominservice\MediaKit\Services;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Services\ImageEngine;
use Dominservice\MediaKit\Support\Kinds\KindRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploader
{
    /**
     * Upload obrazu do wskazanego kind, z polityką replace/keep/delete i filtrami.
     *
     * @param  Model        $model
     * @param  string       $kind
     * @param  UploadedFile $file
     * @param  string       $policy   replace|keep|delete
     * @param  array|null   $filters  np. ['grayscale'] albo [['blur'=>1]]
     */
    public static function uploadImage(Model $model, string $kind, UploadedFile $file, string $policy = 'replace', ?array $filters = null): MediaAsset
    {
        $collection = KindRegistry::collectionFor($kind, $kind);
        $disk = KindRegistry::diskFor($kind, config('media-kit.disk'));

        // 1) polityka usunięcia poprzednich
        if ($policy === 'replace') {
            $model->media()->where('collection', $collection)->delete();
        } elseif ($policy === 'delete') {
            $model->media()->where('collection', $collection)->delete();
        }

        // 2) zapis oryginału
        $path = $file->store('media/originals/'.date('Y/m'), $disk);
        $mime = $file->getClientMimeType();
        $ext  = $file->getClientOriginalExtension() ?: pathinfo($path, PATHINFO_EXTENSION);
        $size = Storage::disk($disk)->size($path);
        [$w, $h] = ImageEngine::dimensions($disk, $path);
        $hash = sha1((string) Storage::disk($disk)->get($path));

        /** @var MediaAsset $asset */
        $asset = $model->media()->create([
            'id'             => (string) Str::uuid(),
            'collection'     => $collection,
            'disk'           => $disk,
            'original_path'  => $path,
            'original_mime'  => $mime,
            'original_ext'   => $ext,
            'original_size'  => $size,
            'width'          => $w,
            'height'         => $h,
            'hash'           => $hash,
            'meta'           => null,
        ]);

        // 3) wstrzyknij filtry do wariantów (tymczasowo nadpisz config tej instancji)
        if ($filters) {
            $variants = config('media-kit.variants', []);
            foreach ($variants as $name => $rules) {
                $variants[$name]['filters'] = $filters;
            }
            config()->set('media-kit.variants', $variants);
        }

        // 4) generuj warianty wg trybu
        if (config('media-kit.mode') === 'eager') {
            ImageEngine::generateAllVariants($asset);
        }

        return $asset;
    }

    /**
     * Podłącz plik wideo jako rendition (basic mode) do kind=video_avatar.
     *
     * @param Model $model
     * @param UploadedFile $file
     * @param string $rendition  hd|sd|mobile (wg konfiguracji)
     */
    public static function uploadVideoRendition(Model $model, UploadedFile $file, string $rendition = 'hd'): ?MediaAsset
    {
        $kind = 'video_avatar';
        $collection = KindRegistry::collectionFor($kind, 'video');
        $disk = KindRegistry::diskFor($kind, config('media-kit.disk'));

        // znajdź lub utwórz asset wideo (kontener meta)
        $asset = $model->media()->where('collection', $collection)->latest()->first();
        if (!$asset) {
            $asset = $model->media()->create([
                'id'             => (string) Str::uuid(),
                'collection'     => $collection,
                'disk'           => $disk,
                'original_path'  => 'video-placeholder/'.date('Y/m').'/'.$model->getKey().'/placeholder.txt',
                'original_mime'  => null,
                'original_ext'   => null,
                'original_size'  => null,
                'width'          => null,
                'height'         => null,
                'hash'           => sha1((string) $model->getKey().uniqid('', true)),
                'meta'           => ['video_renditions' => []],
            ]);
        }

        // zapisz plik wideo
        $path = $file->store('media/videos/'.date('Y/m'), $disk);

        $meta = (array) ($asset->meta ?? []);
        $rends = (array) ($meta['video_renditions'] ?? []);
        $rends[$rendition] = $path;
        $meta['video_renditions'] = $rends;

        $asset->meta = $meta;
        $asset->save();

        return $asset;
    }
}
