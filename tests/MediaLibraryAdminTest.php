<?php

namespace Dominservice\MediaKit\Tests;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Models\MediaFolder;
use Dominservice\MediaKit\Models\MediaLibrary;
use Dominservice\MediaKit\Tests\Support\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Storage;

final class MediaLibraryAdminTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('media-kit.admin.enabled', true);
        $app['config']->set('media-kit.admin.middleware', [SubstituteBindings::class]);
        $app['config']->set('media-kit.admin.library.max_upload_files', 5);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_library_page_renders_folder_navigation_multi_upload_and_bulk_controls(): void
    {
        $library = MediaLibrary::query()->create(['key' => 'client', 'name' => 'Client']);
        MediaFolder::query()->create([
            'media_library_id' => $library->getKey(),
            'key' => 'gallery',
            'name' => 'Gallery',
        ]);

        $this->get(route('admin.media.index', ['library' => $library->getKey()]))
            ->assertOk()
            ->assertSee('Foldery w bibliotece')
            ->assertSee('data-media-filter-library', false)
            ->assertSee('data-media-folder-grid', false)
            ->assertSee('data-media-folder-card="gallery"', false)
            ->assertSee('appendFolderTile(folder)', false)
            ->assertSee('name="files[]"', false)
            ->assertSee('multiple', false)
            ->assertSee('media-bulk-form');
    }

    public function test_library_page_uses_the_direct_url_of_an_existing_public_thumbnail(): void
    {
        $library = MediaLibrary::query()->create(['key' => 'client', 'name' => 'Client']);
        $asset = $this->createAsset($library, 'gallery', 'photo.jpg');
        $thumbnailPath = 'media/variants/photo__thumb.jpeg';
        Storage::disk('public')->put($thumbnailPath, 'thumbnail');
        $asset->variants()->create([
            'name' => 'thumb',
            'format' => 'jpeg',
            'disk' => 'public',
            'path' => $thumbnailPath,
            'width' => 160,
            'height' => 160,
            'size' => 9,
            'generated_at' => now(),
        ]);

        $response = $this->get(route('admin.media.index', ['library' => $library->getKey()]));

        $response
            ->assertSee(Storage::disk('public')->url($thumbnailPath), false)
            ->assertDontSee(route('admin.media.thumbnail', $asset->uuid), false);
    }

    public function test_browser_searches_only_the_selected_library_and_paginates_results(): void
    {
        $selected = MediaLibrary::query()->create(['key' => 'selected', 'name' => 'Selected']);
        $other = MediaLibrary::query()->create(['key' => 'other', 'name' => 'Other']);

        foreach (range(1, 8) as $number) {
            $this->createAsset($selected, 'press', "campaign-{$number}.jpg", ['title' => "Campaign {$number}"]);
        }
        $this->createAsset($selected, 'products', 'unrelated.jpg');
        $this->createAsset($other, 'press', 'campaign-other.jpg');

        $response = $this->getJson(route('admin.media.browse', [
            'library' => $selected->getKey(),
            'q' => 'campaign',
            'per_page' => 6,
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('pagination.total', 8)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('active.library_id', (string) $selected->getKey());

        $this->assertNotContains('campaign-other.jpg', $response->json('data.*.original_path'));
    }

    public function test_browser_filters_reserved_folder_name_and_returns_the_requested_page(): void
    {
        $library = MediaLibrary::query()->create(['key' => 'client', 'name' => 'Client']);

        foreach (range(1, 7) as $number) {
            $this->createAsset($library, 'Miasto & Rejs', "cruise-{$number}.jpg");
        }

        $this->getJson(route('admin.media.browse', [
            'library' => $library->getKey(),
            'collection' => 'Miasto & Rejs',
            'page' => 2,
            'per_page' => 6,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('active.collection', 'Miasto & Rejs')
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.total', 7);
    }

    public function test_missing_alt_filter_returns_only_images_without_alt_text(): void
    {
        $library = MediaLibrary::query()->create(['key' => 'client', 'name' => 'Client']);
        $this->createAsset($library, 'gallery', 'missing.jpg');
        $this->createAsset($library, 'gallery', 'described.jpg', ['alt' => 'Opis']);
        $video = $this->createAsset($library, 'gallery', 'clip.mp4');
        $video->update(['original_mime' => 'video/mp4', 'original_ext' => 'mp4']);

        $this->getJson(route('admin.media.browse', [
            'library' => $library->getKey(),
            'missing_alt' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.original_path', 'missing.jpg');
    }

    public function test_multiple_files_are_uploaded_to_the_chosen_library_and_folder(): void
    {
        $library = MediaLibrary::query()->create(['key' => 'client', 'name' => 'Client']);

        $response = $this->postJson(route('admin.media.store'), [
            'library_id' => $library->getKey(),
            'collection' => 'events',
            'files' => [
                $this->imageUpload('first.png'),
                $this->imageUpload('second.png'),
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonCount(2, 'assets')
            ->assertJsonPath('assets.0.collection', 'events');

        $previewUrl = $response->json('assets.0.preview_url');
        $this->assertIsString($previewUrl);
        $asset = MediaAsset::query()->with('variants')->findOrFail($response->json('assets.0.uuid'));
        $thumbnail = $asset->variants->firstWhere('name', 'thumb');
        $this->assertNotNull($thumbnail);
        $this->assertSame(Storage::disk($thumbnail->disk)->url($thumbnail->path), $previewUrl);
        $this->assertStringNotContainsString('/admin/media/thumbnail/', $previewUrl);

        $this->assertDatabaseCount('media_assets', 2);
        $this->assertDatabaseHas('media_folders', [
            'media_library_id' => $library->getKey(),
            'key' => 'events',
        ]);
        $this->assertSame(2, $library->media()->where('collection', 'events')->count());
    }

    public function test_browser_uses_the_thumbnail_endpoint_only_when_a_public_variant_is_missing(): void
    {
        $library = MediaLibrary::query()->create(['key' => 'client', 'name' => 'Client']);
        $asset = $this->createAsset($library, 'gallery', 'missing-variant.jpg');

        $response = $this->getJson(route('admin.media.browse', [
            'library' => $library->getKey(),
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $asset->uuid);
        $this->assertStringContainsString(
            '/admin/media/thumbnail/'.$asset->uuid,
            (string) $response->json('data.0.preview_url'),
        );
    }

    public function test_library_and_folder_can_be_created_dynamically(): void
    {
        $libraryResponse = $this->postJson(route('admin.media.libraries.store'), [
            'name' => 'Materiały prasowe',
        ])->assertCreated();

        $libraryId = $libraryResponse->json('library.id');

        $this->postJson(route('admin.media.folders.store'), [
            'library_id' => $libraryId,
            'name' => 'Konferencja 2026',
        ])
            ->assertCreated()
            ->assertJsonPath('folder.key', 'konferencja-2026');

        $this->assertDatabaseHas('media_libraries', ['id' => $libraryId, 'key' => 'materialy-prasowe']);
        $this->assertDatabaseHas('media_folders', [
            'media_library_id' => $libraryId,
            'key' => 'konferencja-2026',
        ]);
    }

    public function test_bulk_move_changes_library_and_folder_for_every_selected_asset(): void
    {
        $source = MediaLibrary::query()->create(['key' => 'source', 'name' => 'Source']);
        $target = MediaLibrary::query()->create(['key' => 'target', 'name' => 'Target']);
        $assets = collect([
            $this->createAsset($source, 'old', 'one.jpg'),
            $this->createAsset($source, 'old', 'two.jpg'),
        ]);

        $this->postJson(route('admin.media.bulk.move'), [
            'asset_uuids' => $assets->pluck('uuid')->all(),
            'library_id' => $target->getKey(),
            'collection' => 'archive',
        ])->assertOk();

        foreach ($assets as $asset) {
            $this->assertDatabaseHas('media_assets', [
                'uuid' => $asset->uuid,
                'model_id' => $target->getKey(),
                'collection' => 'archive',
            ]);
        }

        $this->assertDatabaseHas('media_folders', [
            'media_library_id' => $target->getKey(),
            'key' => 'archive',
        ]);
    }

    public function test_bulk_delete_is_rejected_without_deleting_anything_when_one_asset_is_used(): void
    {
        $library = MediaLibrary::query()->create(['key' => 'source', 'name' => 'Source']);
        $used = $this->createAsset($library, 'press', 'used.jpg');
        $unused = $this->createAsset($library, 'press', 'unused.jpg');
        $post = Post::query()->create(['title' => 'Home']);
        $post->attachExistingMedia($used, 'featured');

        $this->postJson(route('admin.media.bulk.destroy'), [
            'asset_uuids' => [$used->uuid, $unused->uuid],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('asset_uuids');

        $this->assertDatabaseHas('media_assets', ['uuid' => $used->uuid]);
        $this->assertDatabaseHas('media_assets', ['uuid' => $unused->uuid]);
    }

    public function test_empty_folder_can_be_renamed_and_deleted(): void
    {
        $library = MediaLibrary::query()->create(['key' => 'client', 'name' => 'Client']);
        $folder = MediaFolder::query()->create([
            'media_library_id' => $library->getKey(),
            'key' => 'drafts',
            'name' => 'Drafts',
        ]);

        $this->putJson(route('admin.media.folders.update', $folder), [
            'name' => 'Approved',
        ])->assertOk()->assertJsonPath('folder.key', 'approved');

        $this->deleteJson(route('admin.media.folders.destroy', $folder->fresh()))->assertOk();
        $this->assertDatabaseMissing('media_folders', ['id' => $folder->getKey()]);
    }

    private function createAsset(MediaLibrary $library, string $collection, string $path, array $meta = []): MediaAsset
    {
        return $library->media()->create([
            'collection' => $collection,
            'disk' => 'public',
            'original_path' => $path,
            'original_mime' => 'image/jpeg',
            'original_ext' => 'jpg',
            'original_size' => 1024,
            'width' => 100,
            'height' => 100,
            'hash' => sha1($path),
            'meta' => $meta,
        ]);
    }

    private function imageUpload(string $name): UploadedFile
    {
        return new UploadedFile(CreatesImage::makePngTemp(32, 32), $name, 'image/png', null, true);
    }
}
