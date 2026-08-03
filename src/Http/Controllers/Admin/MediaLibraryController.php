<?php

namespace Dominservice\MediaKit\Http\Controllers\Admin;

use Dominservice\MediaKit\Models\MediaAsset;
use Dominservice\MediaKit\Services\MediaAssetManager;
use Dominservice\MediaKit\Services\MediaUsageInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MediaLibraryController extends Controller
{
    public function index(Request $request, MediaUsageInspector $usageInspector): View
    {
        $libraryModel = config('media-kit.admin.library.model', \Dominservice\MediaKit\Models\MediaLibrary::class);
        /** @var \Dominservice\MediaKit\Models\MediaLibrary $library */
        $library = $libraryModel::defaultLibrary();
        $search = trim((string) $request->string('q'));
        $collection = trim((string) $request->string('collection'));
        $perPage = max(6, (int) config('media-kit.admin.library.per_page', 18));

        $query = MediaAsset::query()
            ->with(['variants', 'model'])
            ->whereNull('meta->_cloned_from_asset_uuid')
            ->latest();

        if ($collection !== '') {
            $query->where('collection', $collection);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('collection', 'like', '%' . $search . '%')
                    ->orWhere('original_path', 'like', '%' . $search . '%')
                    ->orWhere('original_mime', 'like', '%' . $search . '%')
                    ->orWhere('meta->title', 'like', '%' . $search . '%')
                    ->orWhere('meta->alt', 'like', '%' . $search . '%');
            });
        }

        $assets = $query->paginate($perPage)->withQueryString();

        $collections = MediaAsset::query()
            ->whereNull('meta->_cloned_from_asset_uuid')
            ->select('collection')
            ->distinct()
            ->orderBy('collection')
            ->pluck('collection')
            ->filter()
            ->values();

        return view((string) config('media-kit.admin.library.view', 'mediakit::admin.library.index'), [
            'library' => $library,
            'assets' => $assets,
            'collections' => $collections,
            'search' => $search,
            'selectedCollection' => $collection,
            'mediaKitAdmin' => (array) config('media-kit.admin', []),
            'assetUsages' => $usageInspector->forAssets($assets->getCollection()),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $allowedExtensions = (array) config('media-kit.admin.library.allowed_extensions', ['jpg', 'jpeg', 'png', 'webp', 'avif', 'mp4', 'webm', 'mov']);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:' . implode(',', $allowedExtensions)],
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'string', 'max:100'],
        ]);

        $libraryModel = config('media-kit.admin.library.model', \Dominservice\MediaKit\Models\MediaLibrary::class);
        /** @var \Dominservice\MediaKit\Models\MediaLibrary $library */
        $library = $libraryModel::defaultLibrary();
        $collection = trim((string) ($validated['collection'] ?? config('media-kit.admin.library.default_collection', 'library')));
        $collection = $collection !== '' ? $collection : (string) config('media-kit.admin.library.default_collection', 'library');
        $asset = $library->addMedia($request->file('file'), $collection);

        $meta = (array) ($asset->meta ?? []);
        $meta['title'] = trim((string) ($validated['title'] ?? '')) ?: null;
        $meta['alt'] = trim((string) ($validated['alt'] ?? '')) ?: null;
        $asset->meta = array_filter($meta, static fn ($value) => $value !== null && $value !== '');
        $asset->save();

        $this->logActivity($request, 'media_library_uploaded', $asset, [
            'collection' => $asset->collection,
            'path' => $asset->original_path,
            'mime' => $asset->original_mime,
        ]);

        if ($request->expectsJson()) {
            $previewRouteAvailable = \Illuminate\Support\Facades\Route::has('mediakit.media.show');

            return response()->json([
                'ok' => true,
                'message' => 'Plik został dodany do biblioteki mediów.',
                'asset' => [
                    'uuid' => $asset->uuid,
                    'collection' => $asset->collection,
                    'original_path' => $asset->original_path,
                    'original_mime' => $asset->original_mime,
                    'original_ext' => $asset->original_ext,
                    'title' => (string) data_get($asset->meta, 'title', ''),
                    'alt' => (string) data_get($asset->meta, 'alt', ''),
                    'preview_url' => $previewRouteAvailable && !str_starts_with((string) $asset->original_mime, 'video/')
                        ? route('mediakit.media.show', [$asset->uuid, 'sm'])
                        : null,
                    'full_url' => $previewRouteAvailable
                        ? route('mediakit.media.show', [$asset->uuid, 'lg'])
                        : null,
                ],
            ]);
        }

        return back()->with('status', 'Plik został dodany do biblioteki mediów.');
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

    public function update(Request $request, string $asset): RedirectResponse
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
        $mediaAsset->meta = array_filter($meta, static fn (mixed $value): bool => $value !== null && $value !== '');
        $mediaAsset->save();

        $this->logActivity($request, 'media_library_updated', $mediaAsset, [
            'collection' => $mediaAsset->collection,
        ]);

        return back()->with('status', 'Dane pliku zostały zaktualizowane.');
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
