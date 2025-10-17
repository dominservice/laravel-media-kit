<?php

namespace Dominservice\MediaKit\Tests;

use Dominservice\MediaKit\Services\LegacyCompatibility;
use Illuminate\Support\Facades\Storage;

class LegacyFallbackTest extends TestCase
{
    public function test_legacy_resolve_paths(): void
    {
        $disk = 'public';
        Storage::disk($disk)->put('videos/video_123.mp4', 'x');

        $p = LegacyCompatibility::resolveLegacyVideoPath($disk, '123');
        $this->assertEquals('videos/video_123.mp4', $p);

        Storage::disk($disk)->put('videos/poster_123.jpg', 'x');
        $pp = LegacyCompatibility::resolveLegacyPosterPath($disk, '123');
        $this->assertEquals('videos/poster_123.jpg', $pp);
    }
}
