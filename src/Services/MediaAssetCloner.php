<?php

namespace Dominservice\MediaKit\Services;

use Dominservice\MediaKit\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MediaAssetCloner
{
    public function cloneToModel(MediaAsset|string $asset, Model $model, string $collection, string $policy = 'replace', array $meta = []): MediaAsset
    {
        $source = $asset instanceof MediaAsset
            ? $asset
            : MediaAsset::query()->with('variants')->find($asset);

        if (!$source instanceof MediaAsset) {
            throw new InvalidArgumentException('Source media asset was not found.');
        }

        if ($policy === 'replace') {
            $model->media()->where('collection', $collection)->delete();
        }

        /** @var MediaAsset $target */
        $target = $model->media()->create([
            'uuid' => (string) Str::uuid(),
            'collection' => $collection,
            'disk' => $source->disk,
            'original_path' => $source->original_path,
            'original_mime' => $source->original_mime,
            'original_ext' => $source->original_ext,
            'original_size' => $source->original_size,
            'width' => $source->width,
            'height' => $source->height,
            'hash' => $source->hash,
            'meta' => array_replace_recursive((array) ($source->meta ?? []), $meta, [
                '_cloned_from_asset_uuid' => $source->uuid,
            ]),
        ]);

        foreach ($source->variants as $variant) {
            $target->variants()->create([
                'name' => $variant->name,
                'format' => $variant->format,
                'disk' => $variant->disk,
                'path' => $variant->path,
                'width' => $variant->width,
                'height' => $variant->height,
                'quality' => $variant->quality,
                'size' => $variant->size,
                'generated_at' => $variant->generated_at,
                'meta' => array_replace_recursive((array) ($variant->meta ?? []), [
                    '_cloned_from_asset_uuid' => $source->uuid,
                ]),
            ]);
        }

        return $target->load('variants');
    }
}
