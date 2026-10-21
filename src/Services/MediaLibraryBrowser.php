<?php

namespace Dominservice\MediaKit\Services;

use Dominservice\MediaKit\Models\MediaFolder;
use Dominservice\MediaKit\Models\MediaLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MediaLibraryBrowser
{
    /** @return Collection<int, MediaLibrary> */
    public function libraries(): Collection
    {
        $libraryModel = $this->libraryModel();

        return $libraryModel::query()
            ->withCount(['media as assets_count' => fn (Builder $query): Builder => $this->sourceAssets($query)])
            ->orderBy('name')
            ->get();
    }

    public function resolveLibrary(?string $identifier, bool $fallbackToDefault = true): MediaLibrary
    {
        $identifier = trim((string) $identifier);
        $libraryModel = $this->libraryModel();

        if ($identifier !== '') {
            $library = $libraryModel::query()
                ->whereKey($identifier)
                ->orWhere('key', $identifier)
                ->first();

            if ($library instanceof MediaLibrary) {
                return $library;
            }
        }

        if (! $fallbackToDefault) {
            throw (new ModelNotFoundException)->setModel($libraryModel, [$identifier]);
        }

        return $libraryModel::defaultLibrary();
    }

    /**
     * @return Collection<int, array{key:string,name:string,count:int,id:?string,persisted:bool}>
     */
    public function folders(MediaLibrary $library): Collection
    {
        $persisted = MediaFolder::query()
            ->where('media_library_id', $library->getKey())
            ->orderBy('name')
            ->get()
            ->keyBy('key');

        $counts = $this->sourceAssets($library->media()->getQuery())
            ->selectRaw('collection, COUNT(*) as aggregate')
            ->groupBy('collection')
            ->orderBy('collection')
            ->pluck('aggregate', 'collection');

        $folders = collect($persisted->keys())
            ->merge($counts->keys())
            ->filter(static fn (mixed $key): bool => trim((string) $key) !== '')
            ->unique()
            ->map(function (mixed $key) use ($persisted, $counts): array {
                $folderKey = trim((string) $key);
                $folder = $persisted->get($folderKey);

                return [
                    'key' => $folderKey,
                    'name' => $folder?->name ?: $folderKey,
                    'count' => (int) ($counts->get($folderKey) ?? 0),
                    'id' => $folder?->getKey(),
                    'persisted' => $folder instanceof MediaFolder,
                ];
            })
            ->sortBy(static fn (array $folder): string => Str::lower($folder['name']))
            ->values();

        if ($folders->isEmpty()) {
            $default = trim((string) config('media-kit.admin.library.default_collection', 'library')) ?: 'library';

            return collect([[
                'key' => $default,
                'name' => $default,
                'count' => 0,
                'id' => null,
                'persisted' => false,
            ]]);
        }

        return $folders;
    }

    /** @param array{q?:string,collection?:string,type?:string,missing_alt?:bool,sort?:string} $filters */
    public function assets(MediaLibrary $library, array $filters = []): Builder
    {
        $query = $this->sourceAssets($library->media()->getQuery())->with('variants');
        $collection = trim((string) ($filters['collection'] ?? ''));
        $search = trim((string) ($filters['q'] ?? ''));

        if ($collection !== '') {
            $query->where('collection', $collection);
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('collection', 'like', '%'.$search.'%')
                    ->orWhere('original_path', 'like', '%'.$search.'%')
                    ->orWhere('original_mime', 'like', '%'.$search.'%')
                    ->orWhere('uuid', 'like', '%'.$search.'%')
                    ->orWhere('meta->title', 'like', '%'.$search.'%')
                    ->orWhere('meta->alt', 'like', '%'.$search.'%');
            });
        }

        match ((string) ($filters['type'] ?? '')) {
            'image' => $query->where('original_mime', 'like', 'image/%'),
            'video' => $query->where('original_mime', 'like', 'video/%'),
            default => null,
        };

        if ((bool) ($filters['missing_alt'] ?? false)) {
            $query
                ->where('original_mime', 'like', 'image/%')
                ->where(function (Builder $builder): void {
                    $builder
                        ->whereNull('meta->alt')
                        ->orWhere('meta->alt', '');
                });
        }

        return match ((string) ($filters['sort'] ?? 'newest')) {
            'oldest' => $query->oldest('created_at')->orderBy('uuid'),
            'name' => $query->orderBy('original_path')->orderBy('uuid'),
            'size' => $query->orderByDesc('original_size')->orderBy('uuid'),
            default => $query->latest('created_at')->orderByDesc('uuid'),
        };
    }

    public function materializeFolder(MediaLibrary $library, string $key, ?string $name = null): MediaFolder
    {
        $key = trim($key);

        return MediaFolder::query()->firstOrCreate([
            'media_library_id' => $library->getKey(),
            'key' => $key,
        ], [
            'name' => trim((string) $name) ?: $key,
        ]);
    }

    public function sourceAssets(Builder $query): Builder
    {
        return $query->whereNull('meta->_cloned_from_asset_uuid');
    }

    /** @return class-string<MediaLibrary> */
    private function libraryModel(): string
    {
        /** @var class-string<MediaLibrary> $model */
        $model = config('media-kit.admin.library.model', MediaLibrary::class);

        return $model;
    }
}
