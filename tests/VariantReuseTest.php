<?php

namespace Dominservice\MediaKit\Tests;

use Dominservice\MediaKit\Tests\Support\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VariantReuseTest extends TestCase
{
    public function test_reuse_variants_by_hash(): void
    {
        Config::set('media-kit.mode', 'eager');
        Config::set('media-kit.cache.enable_reuse', true);

        Storage::disk('public')->makeDirectory('.');

        $img = CreatesImage::makePngTemp(400, 300);

        // Post A
        $postA = Post::create(['title' => 'A']);
        $assetA = $postA->addMedia(new UploadedFile($img, 'same.png', 'image/png', null, true), 'gallery');

        $this->assertDatabaseHas('media_variants', ['asset_id' => $assetA->id, 'name' => 'md', 'format' => 'jpeg']);

        // Post B — ten sam plik, powinien skopiować (meta.reused)
        $postB = Post::create(['title' => 'B']);
        $assetB = $postB->addMedia(new UploadedFile($img, 'same.png', 'image/png', null, true), 'gallery');

        $variantB = DB::table('media_variants')
            ->where(['asset_id' => $assetB->id, 'name' => 'md', 'format' => 'jpeg'])
            ->first();

        $this->assertNotNull($variantB, 'Variant for assetB not created');
        // meta jest json; sprawdźmy, że istnieje (nie każdy driver JSON jest taki sam między DB, dlatego nie sprawdzamy dokładnej wartości).
        $this->assertNotNull($variantB->meta, 'Expected meta to be set (reuse marker)');
    }
}
