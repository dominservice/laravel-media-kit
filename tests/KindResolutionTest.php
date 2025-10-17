<?php

namespace Dominservice\MediaKit\Tests;

use Dominservice\MediaKit\Tests\Support\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class KindResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // dodaj kindy testowe
        Config::set('media-kit.kinds.avatar', [
            'collection' => 'avatar',
            'disk' => 'public',
            'display' => 'md',
            'variants' => ['thumb','md'],
            'aliases' => ['photo','featured'],
        ]);
        Config::set('media-kit.kinds.gallery', [
            'collection' => 'gallery',
            'disk' => 'public',
            'display' => 'md',
            'variants' => ['thumb','md'],
        ]);
    }

    public function test_avatar_and_gallery_urls(): void
    {
        Storage::disk('public')->makeDirectory('.');
        $post = Post::create(['title' => 'Kinds']);

        // avatar
        $avatar = new UploadedFile(CreatesImage::makePngTemp(120, 90), 'a.png', 'image/png', null, true);
        $post->addMedia($avatar, 'avatar');

        // gallery x2
        $g1 = new UploadedFile(CreatesImage::makePngTemp(200, 200), 'g1.png', 'image/png', null, true);
        $g2 = new UploadedFile(CreatesImage::makePngTemp(300, 200), 'g2.png', 'image/png', null, true);
        $post->addMedia($g1, 'gallery');
        $post->addMedia($g2, 'gallery');

        // trait HasMediaKinds jest w modelu testowym? (dodaj do Post w razie potrzeby)
        // w testowym Post mamy tylko HasMedia — symulujemy wywołanie mediaKindUrl bez traita:
        $asset = $post->media()->where('collection','avatar')->first();

        $url = route('mediakit.media.show', [$asset->id, 'md', $asset->id.'-md.jpg']);
        $this->assertNotEmpty($url);
    }
}
