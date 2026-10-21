<?php

namespace Dominservice\MediaKit\Http\Controllers\Admin;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Models\MediaFolder;
use Dominservice\MediaKit\Models\MediaLibrary;
use Dominservice\MediaKit\Services\ImageEngine;
use Dominservice\MediaKit\Services\MediaAssetManager;
use Dominservice\MediaKit\Services\MediaLibraryBrowser;
use Dominservice\MediaKit\Services\MediaUsageInspector;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class MediaLibraryController extends Controller
{
    public function index(
        Request $request,
        MediaLibraryBrowser $browser,
        MediaUsageInspector $usageInspector,
    ): View {
        $filters = $this->validatedFilters($request);
        $library = $browser->resolveLibrary($filters['library'] ?? null);
        $perPage = max(6, min(100, (int) config('media-kit.admin.library.per_page', 24)));
        $assets = $browser->assets($library, $filters)->paginate($perPage)->withQueryString();
        $libraries = $browser->libraries();

        return view((string) config('media-kit.admin.library.view', 'mediakit::admin.library.index'), [
            'library' => $library,
            'libraries' => $libraries,
            'assets' => $assets,
            'folders' => $browser->folders($library),
            'foldersByLibrary' => $libraries->mapWithKeys(
                fn (MediaLibrary $item): array => [(string) $item->getKey() => $browser->folders($item)->values()->all()],
            ),
            'filters' => $filters,
            'mediaKitAdmin' => (array) config('media-kit.admin', []),
            'assetUsages' => $usageInspector->forAssets($assets->getCollection()),
        ]);
    }

    public function browse(Request $request, MediaLibraryBrowser $browser): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $library = $browser->resolveLibrary($filters['library'] ?? null);
        $perPage = max(6, min(48, $request->integer(
            'per_page',
            (int) config('media-kit.admin.library.browser_per_page', 24),
        )));
        $assets = $browser->assets($library, $filters)->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $assets->getCollection()->map(fn (MediaAsset $asset): array => $this->assetPayload($asset))->values(),
            'libraries' => $browser->libraries()->map(fn (MediaLibrary $item): array => [
                'id' => (string) $item->getKey(),
                'key' => $item->key,
                'name' => $item->name,
                'assets_count' => (int) ($item->assets_count ?? 0),
            ])->values(),
            'folders' => $browser->folders($library),
            'active' => [
                'library_id' => (string) $library->getKey(),
                'collection' => $filters['collection'] ?? '',
            ],
            'pagination' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
                'from' => $assets->firstItem(),
                'to' => $assets->lastItem(),
            ],
        ]);
    }

    public function thumbnail(string $asset): RedirectResponse|Response
    {
        $mediaAsset = MediaAsset::query()
            ->with('variants')
            ->where('uuid', $asset)
            ->whereNull('meta->_cloned_from_asset_uuid')
            ->firstOrFail();

        abort_unless(str_starts_with((string) $mediaAsset->original_mime, 'image/'), 404);

        $variantName = $this->thumbnailVariantName();
        $format = $this->formatPriority();
        $variant = $mediaAsset->variants
            ->where('name', $variantName)
            ->sortBy(fn ($item): int => ($index = array_search($item->format, $format, true)) === false ? count($format) : $index)
            ->first();

        if (! $variant && $mediaAsset->original_ext !== 'svg') {
            try {
                $rules = (array) config("media-kit.variants.{$variantName}", ['fit' => [320, 320]]);
                $enabledFormats = (array) (config("media-kit.enabled_formats_per_variant.{$variantName}")
                    ?? config('media-kit.enabled_formats_per_variant.*')
                    ?? $format);

                foreach ($enabledFormats as $enabledFormat) {
                    ImageEngine::generateVariant(
                        $mediaAsset,
                        $variantName,
                        $rules,
                        (string) $enabledFormat,
                        $mediaAsset->disk,
                        $mediaAsset->original_path,
                    );
                }

                $mediaAsset->load('variants');
                $variant = $mediaAsset->variants
                    ->where('name', $variantName)
                    ->sortBy(fn ($item): int => ($index = array_search($item->format, $format, true)) === false ? count($format) : $index)
                    ->first();
            } catch (Throwable) {
                $variant = null;
            }
        }

        if ($variant && Storage::disk($variant->disk)->exists($variant->path)) {
            return redirect()->route('mediakit.media.show', [$mediaAsset->uuid, $variantName, $variant->format]);
        }

        if ($mediaAsset->original_ext === 'svg') {
            return redirect()->route('mediakit.media.show', [$mediaAsset->uuid, $variantName]);
        }

        return response($this->thumbnailPlaceholder(), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(
        Request $request,
        MediaLibraryBrowser $browser,
        MediaAssetManager $manager,
    ): RedirectResponse|JsonResponse {
        $allowedExtensions = (array) config('media-kit.admin.library.allowed_extensions', [
            'jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'svg', 'mp4', 'webm', 'mov',
        ]);
        $maxFiles = max(1, min(100, (int) config('media-kit.admin.library.max_upload_files', 20)));
        $maxFileSize = max(1, (int) config('media-kit.admin.library.max_file_size_kb', 51200));

        $validated = $request->validate([
            'library_id' => ['nullable', 'uuid'],
            'file' => ['nullable', 'file', 'mimes:'.implode(',', $allowedExtensions), 'max:'.$maxFileSize],
            'files' => ['nullable', 'array', 'min:1', 'max:'.$maxFiles],
            'files.*' => ['file', 'mimes:'.implode(',', $allowedExtensions), 'max:'.$maxFileSize],
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'string', 'max:100'],
        ]);

        $files = array_values(array_filter([
            ...($request->file('files', [])),
            $request->file('file'),
        ]));

        if ($files === []) {
            throw ValidationException::withMessages(['files' => 'Wybierz co najmniej jeden plik.']);
        }

        if (count($files) > $maxFiles) {
            throw ValidationException::withMessages([
                'files' => "Jednocześnie można przesłać maksymalnie {$maxFiles} plików.",
            ]);
        }

        $library = $browser->resolveLibrary($validated['library_id'] ?? null);
        $collection = trim((string) ($validated['collection'] ?? config('media-kit.admin.library.default_collection', 'library')));
        $collection = $collection !== '' ? $collection : (string) config('media-kit.admin.library.default_collection', 'library');
        $browser->materializeFolder($library, $collection);
        $created = new Collection;

        try {
            foreach ($files as $file) {
                $asset = $library->addMedia($file, $collection);
                $meta = (array) ($asset->meta ?? []);
                $meta['title'] = count($files) === 1
                    ? (trim((string) ($validated['title'] ?? '')) ?: null)
                    : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $meta['alt'] = count($files) === 1
                    ? (trim((string) ($validated['alt'] ?? '')) ?: null)
                    : null;
                $asset->meta = array_filter($meta, static fn (mixed $value): bool => $value !== null && $value !== '');
                $asset->save();
                $asset->load('variants');
                $created->push($asset);
            }
        } catch (Throwable $exception) {
            $created->each(fn (MediaAsset $asset) => $manager->delete($asset));

            throw $exception;
        }

        $this->logActivity($request, 'media_library_uploaded', $created->first(), [
            'library_id' => (string) $library->getKey(),
            'collection' => $collection,
            'asset_uuids' => $created->pluck('uuid')->all(),
            'count' => $created->count(),
        ]);

        $message = trans_choice(
            '{1} Plik został dodany do biblioteki mediów.|[2,4] :count pliki zostały dodane do biblioteki mediów.|[5,*] :count plików zostało dodanych do biblioteki mediów.',
            $created->count(),
            ['count' => $created->count()],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'assets' => $created->map(fn (MediaAsset $asset): array => $this->assetPayload($asset))->values(),
            ], 201);
        }

        return back()->with('status', $message);
    }

    public function storeLibrary(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:100'],
        ]);
        $model = $this->libraryModel();
        $key = $this->uniqueKey((string) ($validated['key'] ?? $validated['name']));

        if ($model::query()->where('key', $key)->exists()) {
            throw ValidationException::withMessages(['key' => 'Biblioteka o takim identyfikatorze już istnieje.']);
        }

        /** @var MediaLibrary $library */
        $library = $model::query()->create([
            'key' => $key,
            'name' => trim((string) $validated['name']),
        ]);

        $this->logActivity($request, 'media_library_created', null, [
            'library_id' => (string) $library->getKey(),
            'key' => $library->key,
        ]);

        $payload = ['id' => (string) $library->getKey(), 'key' => $library->key, 'name' => $library->name];

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'library' => $payload], 201)
            : back()->with('status', 'Biblioteka została utworzona.');
    }

    public function storeFolder(Request $request, MediaLibraryBrowser $browser): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'library_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:100'],
        ]);
        $library = $browser->resolveLibrary((string) $validated['library_id'], false);
        $key = $this->uniqueKey((string) ($validated['key'] ?? $validated['name']));

        if ($browser->folders($library)->contains('key', $key)) {
            throw ValidationException::withMessages(['key' => 'Folder o takim identyfikatorze już istnieje.']);
        }

        $folder = $browser->materializeFolder($library, $key, trim((string) $validated['name']));
        $this->logActivity($request, 'media_folder_created', null, [
            'folder_id' => (string) $folder->getKey(),
            'library_id' => (string) $library->getKey(),
            'key' => $folder->key,
        ]);

        $payload = [
            'id' => (string) $folder->getKey(),
            'library_id' => (string) $library->getKey(),
            'key' => $folder->key,
            'name' => $folder->name,
            'assets_count' => 0,
        ];

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'folder' => $payload], 201)
            : back()->with('status', 'Folder został utworzony.');
    }

    public function updateFolder(Request $request, MediaFolder $folder): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:100'],
        ]);
        $key = $this->uniqueKey((string) ($validated['key'] ?? $validated['name']));
        $libraryModel = $this->libraryModel();
        /** @var MediaLibrary $library */
        $library = $libraryModel::query()->findOrFail($folder->media_library_id);
        $duplicate = MediaFolder::query()
            ->where('media_library_id', $folder->media_library_id)
            ->where('key', $key)
            ->where($folder->getKeyName(), '!=', $folder->getKey())
            ->exists()
            || ($key !== $folder->key && MediaAsset::query()
                ->where('model_type', $library->getMorphClass())
                ->where('model_id', $folder->media_library_id)
                ->whereNull('meta->_cloned_from_asset_uuid')
                ->where('collection', $key)
                ->exists());

        if ($duplicate) {
            throw ValidationException::withMessages(['key' => 'Folder o takim identyfikatorze już istnieje.']);
        }

        $oldKey = $folder->key;

        DB::transaction(function () use ($folder, $key, $oldKey, $validated, $library): void {
            MediaAsset::query()
                ->where('model_type', $library->getMorphClass())
                ->where('model_id', $folder->media_library_id)
                ->whereNull('meta->_cloned_from_asset_uuid')
                ->where('collection', $oldKey)
                ->update(['collection' => $key]);

            $folder->update([
                'key' => $key,
                'name' => trim((string) $validated['name']),
            ]);
        });

        $this->logActivity($request, 'media_folder_updated', null, [
            'folder_id' => (string) $folder->getKey(),
            'old_key' => $oldKey,
            'key' => $key,
        ]);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'folder' => [
                'id' => (string) $folder->getKey(),
                'key' => $folder->key,
                'name' => $folder->name,
            ]])
            : back()->with('status', 'Folder został zaktualizowany.');
    }

    public function destroyFolder(Request $request, MediaFolder $folder): RedirectResponse|JsonResponse
    {
        $libraryModel = $this->libraryModel();
        /** @var MediaLibrary $library */
        $library = $libraryModel::query()->findOrFail($folder->media_library_id);
        $hasAssets = MediaAsset::query()
            ->where('model_type', $library->getMorphClass())
            ->where('model_id', $folder->media_library_id)
            ->whereNull('meta->_cloned_from_asset_uuid')
            ->where('collection', $folder->key)
            ->exists();

        if ($hasAssets) {
            throw ValidationException::withMessages([
                'folder' => 'Folder można usunąć dopiero po przeniesieniu lub usunięciu wszystkich plików.',
            ]);
        }

        $folderId = (string) $folder->getKey();
        $folder->delete();
        $this->logActivity($request, 'media_folder_deleted', null, ['folder_id' => $folderId]);

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('status', 'Folder został usunięty.');
    }

    public function bulkMove(Request $request, MediaLibraryBrowser $browser): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'asset_uuids' => ['required', 'array', 'min:1', 'max:500'],
            'asset_uuids.*' => ['required', 'uuid', 'distinct'],
            'library_id' => ['required', 'uuid'],
            'collection' => ['required', 'string', 'max:100'],
        ]);
        $assets = $this->selectedLibraryAssets($validated['asset_uuids']);
        $this->assertAllAssetsFound($assets, $validated['asset_uuids']);
        $library = $browser->resolveLibrary((string) $validated['library_id'], false);
        $collection = trim((string) $validated['collection']);
        if ($collection === '') {
            throw ValidationException::withMessages(['collection' => 'Wybierz folder docelowy.']);
        }
        $browser->materializeFolder($library, $collection);

        DB::transaction(function () use ($assets, $library, $collection): void {
            MediaAsset::query()
                ->whereKey($assets->modelKeys())
                ->update([
                    'model_type' => $library->getMorphClass(),
                    'model_id' => $library->getKey(),
                    'collection' => $collection,
                ]);
        });

        $this->logActivity($request, 'media_library_bulk_moved', null, [
            'asset_uuids' => $assets->pluck('uuid')->all(),
            'library_id' => (string) $library->getKey(),
            'collection' => $collection,
        ]);
        $message = 'Przeniesiono wybrane pliki: '.$assets->count().'.';

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message])
            : back()->with('status', $message);
    }

    public function bulkDestroy(
        Request $request,
        MediaAssetManager $manager,
        MediaUsageInspector $usageInspector,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'asset_uuids' => ['required', 'array', 'min:1', 'max:500'],
            'asset_uuids.*' => ['required', 'uuid', 'distinct'],
        ]);
        $assets = $this->selectedLibraryAssets($validated['asset_uuids'], true);
        $this->assertAllAssetsFound($assets, $validated['asset_uuids']);
        $usages = $usageInspector->forAssets($assets);
        $used = $assets->filter(fn (MediaAsset $asset): bool => ($usages[$asset->uuid] ?? []) !== []);

        if ($used->isNotEmpty()) {
            throw ValidationException::withMessages([
                'asset_uuids' => 'Nie usunięto żadnego pliku. Część zaznaczonych plików jest przypisana do treści: '
                    .$used->pluck('uuid')->implode(', ').'.',
            ]);
        }

        $uuids = $assets->pluck('uuid')->all();
        $assets->each(fn (MediaAsset $asset) => $manager->delete($asset));
        $this->logActivity($request, 'media_library_bulk_deleted', null, [
            'asset_uuids' => $uuids,
            'count' => count($uuids),
        ]);
        $message = 'Usunięto wybrane pliki: '.count($uuids).'.';

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message])
            : back()->with('status', $message);
    }

    public function destroy(Request $request, string $asset, MediaAssetManager $manager, MediaUsageInspector $usageInspector): RedirectResponse
    {
        $mediaAsset = MediaAsset::query()
            ->with('variants')
            ->where('uuid', $asset)
            ->whereNull('meta->_cloned_from_asset_uuid')
            ->firstOrFail();

        if ($usageInspector->forAsset($mediaAsset) !== []) {
            return back()->withErrors([
                'media' => 'Nie można usunąć pliku, ponieważ jest przypisany do treści. Najpierw usuń jego powiązania.',
            ]);
        }

        $manager->delete($mediaAsset);

        $this->logActivity($request, 'media_library_deleted', null, [
            'asset_uuid' => $asset,
            'collection' => $mediaAsset->collection,
            'path' => $mediaAsset->original_path,
        ]);

        return back()->with('status', 'Plik został usunięty z biblioteki mediów.');
    }

    public function update(Request $request, string $asset, MediaLibraryBrowser $browser): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'collection' => ['required', 'string', 'max:100'],
        ]);

        $mediaAsset = MediaAsset::query()
            ->where('uuid', $asset)
            ->whereNull('meta->_cloned_from_asset_uuid')
            ->firstOrFail();
        $meta = (array) ($mediaAsset->meta ?? []);
        $meta['title'] = trim((string) ($validated['title'] ?? '')) ?: null;
        $meta['alt'] = trim((string) ($validated['alt'] ?? '')) ?: null;
        $mediaAsset->collection = trim((string) $validated['collection']);
        if ($mediaAsset->collection === '') {
            throw ValidationException::withMessages(['collection' => 'Wybierz folder.']);
        }
        $mediaAsset->meta = array_filter($meta, static fn (mixed $value): bool => $value !== null && $value !== '');
        $mediaAsset->save();

        if ($mediaAsset->model instanceof MediaLibrary) {
            $browser->materializeFolder($mediaAsset->model, $mediaAsset->collection);
        }

        $this->logActivity($request, 'media_library_updated', $mediaAsset, [
            'collection' => $mediaAsset->collection,
        ]);

        return back()->with('status', 'Dane pliku zostały zaktualizowane.');
    }

    /** @return array{q:string,library:?string,collection:string,type:string,missing_alt:bool,sort:string} */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'library' => ['nullable', 'string', 'max:100'],
            'collection' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:image,video'],
            'missing_alt' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:newest,oldest,name,size'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:6', 'max:100'],
        ]);

        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'library' => isset($validated['library']) ? trim((string) $validated['library']) : null,
            'collection' => trim((string) ($validated['collection'] ?? '')),
            'type' => (string) ($validated['type'] ?? ''),
            'missing_alt' => (bool) ($validated['missing_alt'] ?? false),
            'sort' => (string) ($validated['sort'] ?? 'newest'),
        ];
    }

    /** @return array<string, mixed> */
    private function assetPayload(MediaAsset $asset): array
    {
        $asset->loadMissing('variants');
        $isImage = str_starts_with((string) $asset->original_mime, 'image/');
        $thumbnailRoute = $this->adminRoute('thumbnail');

        return [
            'uuid' => $asset->uuid,
            'library_id' => (string) $asset->model_id,
            'collection' => $asset->collection,
            'original_path' => $asset->original_path,
            'original_mime' => $asset->original_mime,
            'original_ext' => $asset->original_ext,
            'original_size' => $asset->original_size,
            'width' => $asset->width,
            'height' => $asset->height,
            'title' => (string) data_get($asset->meta, 'title', ''),
            'alt' => (string) data_get($asset->meta, 'alt', ''),
            'preview_url' => $isImage && Route::has($thumbnailRoute)
                ? route($thumbnailRoute, $asset->uuid)
                : null,
            'full_url' => str_starts_with((string) $asset->original_mime, 'video/')
                ? Storage::disk($asset->disk)->url($asset->original_path)
                : (Route::has('mediakit.media.show')
                    ? route('mediakit.media.show', [$asset->uuid, 'lg'])
                    : null),
            'variants' => $asset->variants->map(fn ($variant): array => [
                'name' => $variant->name,
                'format' => $variant->format,
                'width' => $variant->width,
                'height' => $variant->height,
                'size' => $variant->size,
                'url' => Route::has('mediakit.media.show')
                    ? route('mediakit.media.show', [$asset->uuid, $variant->name, $variant->format])
                    : null,
            ])->values(),
        ];
    }

    /**
     * @param  array<int, string>  $uuids
     * @return Collection<int, MediaAsset>
     */
    private function selectedLibraryAssets(array $uuids, bool $withVariants = false): Collection
    {
        $model = $this->libraryModel();
        $library = new $model;
        $query = MediaAsset::query()
            ->whereIn('uuid', $uuids)
            ->where('model_type', $library->getMorphClass())
            ->whereNull('meta->_cloned_from_asset_uuid');

        if ($withVariants) {
            $query->with('variants');
        }

        return $query->get();
    }

    /** @param array<int, string> $uuids */
    private function assertAllAssetsFound(Collection $assets, array $uuids): void
    {
        if ($assets->count() !== count(array_unique($uuids))) {
            throw ValidationException::withMessages([
                'asset_uuids' => 'Co najmniej jeden z wybranych plików nie istnieje lub nie należy do biblioteki mediów.',
            ]);
        }
    }

    private function thumbnailVariantName(): string
    {
        $configured = array_keys((array) config('media-kit.variants', []));
        $preferred = (string) config('media-kit.admin.library.thumbnail_variant', 'thumb');

        foreach ([$preferred, 'thumb', 'mobile_thumb', 'tablet_thumb', 'desktop_thumb', 'sm'] as $candidate) {
            if (in_array($candidate, $configured, true)) {
                return $candidate;
            }
        }

        return (string) ($configured[0] ?? 'thumb');
    }

    /** @return array<int, string> */
    private function formatPriority(): array
    {
        return array_values(array_map(
            static fn (mixed $format): string => (string) $format,
            (array) config('media-kit.formats_priority', ['avif', 'webp', 'jpeg', 'png']),
        ));
    }

    private function uniqueKey(string $value): string
    {
        $key = Str::slug($value);

        if ($key === '') {
            throw ValidationException::withMessages(['key' => 'Podaj nazwę zawierającą litery lub cyfry.']);
        }

        return Str::limit($key, 100, '');
    }

    /** @return class-string<MediaLibrary> */
    private function libraryModel(): string
    {
        /** @var class-string<MediaLibrary> $model */
        $model = config('media-kit.admin.library.model', MediaLibrary::class);

        return $model;
    }

    private function adminRoute(string $suffix): string
    {
        return rtrim((string) config('media-kit.admin.route_name_prefix', 'admin.media.'), '.').'.'.$suffix;
    }

    private function thumbnailPlaceholder(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="320" height="240" viewBox="0 0 320 240" role="img" aria-label="Brak miniatury">
  <rect width="320" height="240" fill="#edf1f5"/>
  <path d="M98 168l42-48 28 31 22-25 34 42H98z" fill="#aeb8c4"/>
  <circle cx="126" cy="92" r="15" fill="#aeb8c4"/>
</svg>
SVG;
    }

    private function logActivity(Request $request, string $event, ?MediaAsset $asset, array $properties = []): void
    {
        if (! function_exists('activity')) {
            return;
        }

        $logger = activity('media.library');

        if ($request->user()) {
            $logger->causedBy($request->user());
        }

        if ($asset) {
            $logger->performedOn($asset);
        }

        $logger->withProperties($properties)->log($event);
    }
}
