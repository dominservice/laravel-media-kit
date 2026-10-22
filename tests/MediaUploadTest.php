<?php

namespace Dominservice\MediaKit\Tests;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Services\MediaAssetManager;
use Dominservice\MediaKit\Tests\Support\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
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
        $this->assertDatabaseHas('media_assets', ['uuid' => $asset->uuid, 'collection' => 'featured']);

        // Eager: powinny powstać warianty (jpeg)
        $this->assertDatabaseHas('media_variants', ['asset_uuid' => $asset->uuid, 'name' => 'thumb', 'format' => 'jpeg']);
        $this->assertDatabaseHas('media_variants', ['asset_uuid' => $asset->uuid, 'name' => 'md',    'format' => 'jpeg']);
    }

    public function test_oversized_jpeg_is_optimized_before_it_is_stored(): void
    {
        Config::set('media-kit.mode', 'lazy');
        Config::set('media-kit.originals.enabled', true);
        Config::set('media-kit.originals.max_dimension', 320);
        Config::set('media-kit.originals.max_file_size_kb', 0);
        Config::set('media-kit.originals.jpeg_quality', 85);
        Storage::disk('public')->makeDirectory('.');
        $post = Post::create(['title' => 'Optimized']);
        $source = CreatesImage::makeJpegTemp(800, 600);
        $sourceSize = filesize($source);

        $asset = $post->addMedia(new UploadedFile($source, 'large.jpg', 'image/jpeg', null, true), 'gallery');

        $stored = Storage::disk('public')->path($asset->original_path);
        $this->assertSame(320, $asset->width);
        $this->assertSame(240, $asset->height);
        $this->assertLessThan($sourceSize, $asset->original_size);
        $this->assertSame(sha1_file($stored), $asset->hash);
        $this->assertSame([320, 240], array_slice(getimagesize($stored), 0, 2));
    }

    public function test_identical_uploads_share_one_original_without_breaking_deletion(): void
    {
        Config::set('media-kit.mode', 'lazy');
        Config::set('media-kit.originals.enabled', true);
        Config::set('media-kit.originals.deduplicate', true);
        Config::set('media-kit.originals.max_dimension', 320);
        Config::set('media-kit.originals.max_file_size_kb', 0);
        Storage::disk('public')->makeDirectory('.');
        $source = CreatesImage::makeJpegTemp(800, 600);
        $firstPost = Post::create(['title' => 'First']);
        $secondPost = Post::create(['title' => 'Second']);

        $first = $firstPost->addMedia(new UploadedFile($source, 'first.jpg', 'image/jpeg', null, true), 'gallery');
        $second = $secondPost->addMedia(new UploadedFile($source, 'second.jpg', 'image/jpeg', null, true), 'gallery');

        $this->assertSame($first->original_path, $second->original_path);
        Storage::disk('public')->assertExists($first->original_path);

        $manager = app(MediaAssetManager::class);
        $manager->delete($first);
        Storage::disk('public')->assertExists($second->original_path);

        $manager->delete($second);
        Storage::disk('public')->assertMissing($second->original_path);
    }

    public function test_jpeg_upload_does_not_generate_larger_png_variants(): void
    {
        Config::set('media-kit.mode', 'eager');
        Config::set('media-kit.enabled_formats_per_variant', ['*' => ['webp', 'png', 'jpeg']]);
        Config::set('media-kit.generation.skip_png_for_jpeg_sources', true);
        Storage::disk('public')->makeDirectory('.');
        $post = Post::create(['title' => 'Formats']);
        $source = CreatesImage::makeJpegTemp(640, 480);

        $asset = $post->addMedia(new UploadedFile($source, 'photo.jpg', 'image/jpeg', null, true), 'gallery');

        $this->assertDatabaseHas('media_variants', ['asset_uuid' => $asset->uuid, 'name' => 'thumb', 'format' => 'webp']);
        $this->assertDatabaseHas('media_variants', ['asset_uuid' => $asset->uuid, 'name' => 'thumb', 'format' => 'jpeg']);
        $this->assertDatabaseMissing('media_variants', ['asset_uuid' => $asset->uuid, 'format' => 'png']);
    }
}
