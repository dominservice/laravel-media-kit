<?php

namespace Dominservice\MediaKit\Services;

use Dominservice\MediaKit\Contracts\MediaUsageResolverContract;
use Dominservice\MediaKit\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaUsageInspector
{
    /**
     * @param  iterable<int, MediaAsset>  $assets
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function forAssets(iterable $assets): array
    {
        $assets = collect($assets)->values();
        $sourceUuids = $assets->pluck('uuid')->filter()->values();
        $clones = $sourceUuids->isEmpty()
            ? collect()
            : MediaAsset::query()
                ->with('model')
                ->whereIn('meta->_cloned_from_asset_uuid', $sourceUuids->all())
                ->get()
                ->groupBy(fn (MediaAsset $asset): string => (string) data_get($asset->meta, '_cloned_from_asset_uuid', ''));

        return $assets->mapWithKeys(function (MediaAsset $asset) use ($clones): array {
            $usages = collect();

            if (! $this->isLibraryAsset($asset)) {
                $usages->push($this->resolve($asset));
            }

            foreach ($clones->get($asset->uuid, collect()) as $clone) {
                $usages->push($this->resolve($clone));
            }

            return [$asset->uuid => $usages->values()->all()];
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function forAsset(MediaAsset $asset): array
    {
        return $this->forAssets([$asset])[$asset->uuid] ?? [];
    }

    public function isClone(MediaAsset $asset): bool
    {
        return trim((string) data_get($asset->meta, '_cloned_from_asset_uuid', '')) !== '';
    }

    private function isLibraryAsset(MediaAsset $asset): bool
    {
        return $asset->model_type === config('media-kit.admin.library.model', \Dominservice\MediaKit\Models\MediaLibrary::class);
    }

    /** @return array<string, mixed> */
    private function resolve(MediaAsset $usageAsset): array
    {
        $owner = $usageAsset->relationLoaded('model') ? $usageAsset->model : $usageAsset->model()->first();
        $fallback = [
            'asset_uuid' => $usageAsset->uuid,
            'owner_type' => $usageAsset->model_type,
            'owner_id' => $usageAsset->model_id,
            'label' => $this->ownerLabel($owner, $usageAsset),
            'location' => $usageAsset->collection,
            'url' => null,
        ];

        $resolverClass = config('media-kit.admin.library.usage_resolver');
        if (! is_string($resolverClass) || $resolverClass === '' || ! class_exists($resolverClass)) {
            return $fallback;
        }

        $resolver = app($resolverClass);
        if (! $resolver instanceof MediaUsageResolverContract) {
            return $fallback;
        }

        return array_replace($fallback, $resolver->resolve($usageAsset, $fallback));
    }

    private function ownerLabel(?Model $owner, MediaAsset $usageAsset): string
    {
        if (! $owner) {
            return class_basename($usageAsset->model_type).' #'.$usageAsset->model_id;
        }

        foreach (['name', 'title', 'label', 'slug'] as $attribute) {
            $value = trim((string) $owner->getAttribute($attribute));
            if ($value !== '') {
                return $value;
            }
        }

        return Str::headline(class_basename($owner)).' #'.$owner->getKey();
    }
}
