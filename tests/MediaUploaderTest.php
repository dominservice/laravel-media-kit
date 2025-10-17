<?php

namespace Dominservice\MediaKit\Tests;

use Dominservice\MediaKit\Services\MediaUploader;
use Dominservice\MediaKit\Tests\Support\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class MediaUploaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('media-kit.kinds.avatar', [
            'collection' => 'avatar',
            'disk' => 'public',
            'display' => 'md',
            'variants' => ['thumb','md'],
        ]);

        Config::set('media-kit.kinds.video_avatar', [
            'collection' => 'video',
            'disk' => 'public',
            'renditions' => ['hd','sd','mobile'],
            'poster_kind' => 'video_poster',
        ]);

        Config::set('media-kit.kinds.video_poster', [
            'collection' => 'video_poster',
            'disk' => 'public',
            'display' => 'md',
            'variants' => ['thumb','md'],
        ]);
    }

    public function test_upload_image_with_replace_policy(): void
    {
        Storage::disk('public')->makeDirectory('.');
        $post = Post::create(['title' => 'Upload']);

        $img1 = new UploadedFile(CreatesImage::makePngTemp(120, 90), 'a.png', 'image/png', null, true);
        $asset1 = MediaUploader::uploadImage($post, 'avatar', $img1, 'replace');
        $this->assertDatabaseHas('media_assets', ['id' => $asset1->uuid, 'collection' => 'avatar']);

        $img2 = new UploadedFile(CreatesImage::makePngTemp(200, 120), 'b.png', 'image/png', null, true);
        $asset2 = MediaUploader::uploadImage($post, 'avatar', $img2, 'replace');
        // poprzedni powinien zniknąć
        $this->assertDatabaseMissing('media_assets', ['id' => $asset1->uuid]);
        $this->assertDatabaseHas('media_assets', ['id' => $asset2->uuid, 'collection' => 'avatar']);
    }

    public function test_upload_video_renditions_basic(): void
    {
        Storage::disk('public')->makeDirectory('.');
        $post = Post::create(['title' => 'Video']);

        $hd = new UploadedFile(self::fakeBinary('mp4'), 'v_hd.mp4', 'video/mp4', null, true);
        $sd = new UploadedFile(self::fakeBinary('mp4'), 'v_sd.mp4', 'video/mp4', null, true);

        $asset = MediaUploader::uploadVideoRendition($post, $hd, 'hd');
        $asset = MediaUploader::uploadVideoRendition($post, $sd, 'sd');

        $this->assertNotNull($asset->meta['video_renditions']['hd'] ?? null);
        $this->assertNotNull($asset->meta['video_renditions']['sd'] ?? null);
    }

    protected static function fakeBinary(string $ext): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vid_') . '.' . $ext;
        file_put_contents($tmp, random_bytes(128));
        return $tmp;
    }
}
