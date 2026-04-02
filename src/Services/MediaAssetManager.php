<?php

namespace Dominservice\MediaKit\Services;

use Dominservice\MediaKit\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

class MediaAssetManager
{
    public function delete(MediaAsset $asset): void
    {
        foreach ($asset->variants as $variant) {
            try {
                Storage::disk($variant->disk)->delete($variant->path);
            } catch (\Throwable) {
            }
        }

        try {
            Storage::disk($asset->disk)->delete($asset->original_path);
        } catch (\Throwable) {
        }

        $asset->variants()->delete();
        $asset->delete();
    }
}
