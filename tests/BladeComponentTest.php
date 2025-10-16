<?php

namespace Dominservice\MediaKit\Tests;

use Dominservice\MediaKit\Tests\Support\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class BladeComponentTest extends TestCase
{
    public function test_media_picture_renders_sources(): void
    {
        $post = Post::create(['title' => 'Blade']);
        $path = CreatesImage::makePngTemp(120, 90);
        $asset = $post->addMedia(new UploadedFile($path, 'pic.png', 'image/png', null, true), 'featured');

        $html = view('mediakit::components.media-picture', [
            'asset' => $asset,
            'variant' => 'md',
            'alt' => 'ALT',
            'class' => 'rounded',
        ])->render();

        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('type="image/avif"', $html); // source — nawet jeśli nie istnieje plik, ścieżka jest generowana
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('ALT', $html);
    }
}
