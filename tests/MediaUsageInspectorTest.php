<?php

namespace Dominservice\MediaKit\Tests;

use Dominservice\MediaKit\Models\MediaLibrary;
use Dominservice\MediaKit\Services\MediaUsageInspector;
use Dominservice\MediaKit\Tests\Support\Models\Post;
use Illuminate\Http\UploadedFile;

class MediaUsageInspectorTest extends TestCase
{
    public function test_it_reports_cloned_assignments_and_leaves_unused_assets_empty(): void
    {
        $library = MediaLibrary::defaultLibrary();
        $firstPath = CreatesImage::makePngTemp(80, 60);
        $secondPath = CreatesImage::makePngTemp(40, 30);
        $used = $library->addMedia(new UploadedFile($firstPath, 'used.png', 'image/png', null, true), 'dataset');
        $unused = $library->addMedia(new UploadedFile($secondPath, 'unused.png', 'image/png', null, true), 'dataset');
        $post = Post::create(['title' => 'Hero strony głównej']);
        $post->attachExistingMedia($used, 'avatar');

        $result = app(MediaUsageInspector::class)->forAssets([$used, $unused]);

        $this->assertCount(1, $result[$used->uuid]);
        $this->assertSame('Hero strony głównej', $result[$used->uuid][0]['label']);
        $this->assertSame('avatar', $result[$used->uuid][0]['location']);
        $this->assertSame([], $result[$unused->uuid]);
    }

    public function test_direct_model_asset_is_reported_as_usage(): void
    {
        $post = Post::create(['title' => 'Artykuł']);
        $path = CreatesImage::makePngTemp(80, 60);
        $asset = $post->addMedia(new UploadedFile($path, 'article.png', 'image/png', null, true), 'featured');

        $usage = app(MediaUsageInspector::class)->forAsset($asset);

        $this->assertCount(1, $usage);
        $this->assertSame('Artykuł', $usage[0]['label']);
    }
}
