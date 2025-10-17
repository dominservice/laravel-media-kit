<?php

namespace Dominservice\MediaKit\Tests;

use Dominservice\MediaKit\Tests\Support\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

class MediaLazyGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // W tym teście — tryb lazy
        Config::set('media-kit.mode', 'lazy');
    }

    public function test_lazy_route_generates_variant_on_first_request(): void
    {
        Storage::disk('public')->makeDirectory('.');
        $post = Post::create(['title' => 'World']);

        $path = CreatesImage::makePngTemp(300, 200);
        $uploaded = new UploadedFile($path, 'lazy.png', 'image/png', null, true);

        $asset = $post->addMedia($uploaded, 'featured');

        // przed wywołaniem route wariant 'md' nie istnieje
        $this->assertDatabaseMissing('media_variants', ['asset_uuid' => $asset->uuid, 'name' => 'md', 'format' => 'jpeg']);

        $url = route('mediakit.media.show', [$asset->uuid, 'md', $asset->uuid.'-md.jpg']);
        $resp = $this->get($url);
        $resp->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // po wywołaniu powinien istnieć
        $this->assertDatabaseHas('media_variants', ['asset_uuid' => $asset->uuid, 'name' => 'md', 'format' => 'jpeg']);
    }
}
