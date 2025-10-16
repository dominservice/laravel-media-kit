<?php

namespace Dominservice\MediaKit\Tests;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Tests\Support\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaUploadTest extends TestCase
{
    public function test_add_media_creates_asset_and_variants_in_eager_mode(): void
    {
        Storage::disk('public')->makeDirectory('.');
        $post = Post::create(['title' => 'Hello']);

        $path = CreatesImage::makePngTemp(80, 60);
        $uploaded = new UploadedFile($path, 'test.png', 'image/png', null, true);

        $asset = $post->addMedia($uploaded, 'featured');

        $this->assertInstanceOf(MediaAsset::class, $asset);
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'collection' => 'featured']);

        // Eager: powinny powstać warianty (jpeg)
        $this->assertDatabaseHas('media_variants', ['asset_id' => $asset->id, 'name' => 'thumb', 'format' => 'jpeg']);
        $this->assertDatabaseHas('media_variants', ['asset_id' => $asset->id, 'name' => 'md',    'format' => 'jpeg']);
    }
}
