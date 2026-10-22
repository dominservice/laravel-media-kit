@extends('mediakit::layouts.admin')

@php($routePrefix = rtrim(config('media-kit.admin.route_name_prefix', 'admin.media.'), '.'))
@php($uploadAccept = (string) config('media-kit.admin.library.accept', 'image/*,video/*'))
@php($selectedLibrary = (string) $library->getKey())
@php($selectedCollection = (string) ($filters['collection'] ?? ''))
@php($activeFolder = $folders->firstWhere('key', $selectedCollection))
@php($defaultCollection = (string) config('media-kit.admin.library.default_collection', 'library'))
@php($folderNavigationFilters = ['q' => $filters['q'], 'type' => $filters['type'], 'sort' => $filters['sort'], 'missing_alt' => $filters['missing_alt'] ? 1 : null])

@section('content')
    <div class="d-flex flex-column gap-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-6">
                <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 mb-5">
                    <div>
                        <h1 class="fs-2 fw-bold mb-1">Biblioteka mediów</h1>
                        <div class="text-muted">Pliki wielokrotnego użytku, uporządkowane w bibliotekach i folderach.</div>
                    </div>
                    <form method="get" action="{{ route($routePrefix . '.index') }}" class="row g-2 w-100 w-xl-auto" style="max-width: 920px;">
                        <div class="col-12 col-md-4">
                            <label for="media_filter_q" class="visually-hidden">Szukaj plików</label>
                            <input id="media_filter_q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-solid" placeholder="Nazwa, opis, typ lub UUID">
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="media_filter_library" class="visually-hidden">Biblioteka</label>
                            <select id="media_filter_library" name="library" class="form-select form-select-solid" data-media-filter-library>
                                @foreach($libraries as $item)
                                    <option value="{{ $item->getKey() }}" @selected($selectedLibrary === (string) $item->getKey())>{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label for="media_filter_type" class="visually-hidden">Typ pliku</label>
                            <select id="media_filter_type" name="type" class="form-select form-select-solid">
                                <option value="">Wszystkie typy</option>
                                <option value="image" @selected($filters['type'] === 'image')>Grafiki</option>
                                <option value="video" @selected($filters['type'] === 'video')>Wideo</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label for="media_filter_sort" class="visually-hidden">Sortowanie</label>
                            <select id="media_filter_sort" name="sort" class="form-select form-select-solid">
                                <option value="newest" @selected($filters['sort'] === 'newest')>Najnowsze</option>
                                <option value="oldest" @selected($filters['sort'] === 'oldest')>Najstarsze</option>
                                <option value="name" @selected($filters['sort'] === 'name')>Nazwa</option>
                                <option value="size" @selected($filters['sort'] === 'size')>Rozmiar</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-1">
                            <button type="submit" class="btn btn-light-primary w-100" aria-label="Zastosuj filtry">Szukaj</button>
                        </div>
                        <input type="hidden" name="collection" value="{{ $selectedCollection }}">
                        <div class="col-12">
                            <label class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="checkbox" name="missing_alt" value="1" @checked($filters['missing_alt'])>
                                <span class="form-check-label">Tylko grafiki bez tekstu alternatywnego</span>
                            </label>
                        </div>
                    </form>
                </div>

                @if(session('status'))
                    <div class="alert alert-success mb-5">{{ session('status') }}</div>
                @endif

                @if(isset($errors) && $errors->any())
                    <div class="alert alert-danger mb-5">
                        <div class="fw-semibold mb-2">Could not save the file.</div>
                        <ul class="mb-0 ps-4">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <section class="mb-6" aria-labelledby="media-folders-heading">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <h2 id="media-folders-heading" class="h4 mb-1">Foldery w bibliotece „{{ $library->name }}”</h2>
                            <div class="small text-muted">Wybierz folder, aby zawęzić pliki. Wyszukiwarka działa także między folderami.</div>
                        </div>
                        @if($selectedCollection !== '')
                            <a href="{{ route($routePrefix . '.index', array_filter(['library' => $selectedLibrary, 'q' => $filters['q'], 'type' => $filters['type'], 'sort' => $filters['sort'], 'missing_alt' => $filters['missing_alt'] ? 1 : null])) }}" class="btn btn-sm btn-light-primary">
                                Wszystkie foldery
                            </a>
                        @endif
                    </div>

                    <div class="row g-3" data-media-folder-grid>
                        @foreach($folders as $folder)
                            <div class="col-6 col-md-4 col-xl-3" data-media-folder-card="{{ $folder['key'] }}">
                                <a href="{{ route($routePrefix . '.index', array_filter(['library' => $selectedLibrary, 'collection' => $folder['key'], 'q' => $filters['q'], 'type' => $filters['type'], 'sort' => $filters['sort'], 'missing_alt' => $filters['missing_alt'] ? 1 : null])) }}"
                                   class="d-flex align-items-center gap-3 rounded-3 border p-3 text-gray-800 text-hover-primary h-100 {{ $selectedCollection === $folder['key'] ? 'border-primary bg-light-primary' : 'bg-light' }}"
                                   @if($selectedCollection === $folder['key']) aria-current="page" @endif>
                                    <span class="fs-2" aria-hidden="true">📁</span>
                                    <span class="min-w-0">
                                        <span class="d-block fw-semibold text-truncate">{{ $folder['name'] }}</span>
                                        <span class="d-block small text-muted">{{ $folder['count'] }} plików</span>
                                    </span>
                                </a>
                            </div>
                        @endforeach
                    </div>

                    @if($activeFolder && $activeFolder['persisted'])
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <details>
                                <summary class="btn btn-sm btn-light">Zmień nazwę folderu</summary>
                                <form method="post" action="{{ route($routePrefix . '.folders.update', $activeFolder['id']) }}" class="d-flex flex-wrap gap-2 mt-2">
                                    @csrf
                                    @method('PUT')
                                    <label for="media_folder_rename" class="visually-hidden">Nowa nazwa folderu</label>
                                    <input id="media_folder_rename" type="text" name="name" value="{{ $activeFolder['name'] }}" class="form-control form-control-sm" required>
                                    <button type="submit" class="btn btn-sm btn-primary">Zapisz</button>
                                </form>
                            </details>
                            <form method="post" action="{{ route($routePrefix . '.folders.destroy', $activeFolder['id']) }}" onsubmit="return confirm('Usunąć pusty folder?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-light-danger" @disabled($activeFolder['count'] > 0)>Usuń pusty folder</button>
                            </form>
                        </div>
                    @endif
                </section>

                <div class="row g-5">
                    <div class="col-12 col-xl-4">
                        <div class="rounded-4 border border-dashed border-gray-300 p-5 h-100 bg-light">
                            <h2 class="h4 mb-2">Dodaj pliki</h2>
                            <p class="text-muted mb-4">Możesz przesłać maksymalnie {{ config('media-kit.admin.library.max_upload_files', 20) }} plików jednocześnie.</p>

                            <form method="post" action="{{ route($routePrefix . '.store') }}" enctype="multipart/form-data" class="d-flex flex-column gap-4">
                                @csrf
                                <div>
                                    <label class="form-label fw-semibold" for="mediakit_files">Pliki</label>
                                    <input id="mediakit_files" type="file" name="files[]" class="form-control form-control-solid" accept="{{ $uploadAccept }}" multiple required>
                                </div>
                                <div>
                                    <label class="form-label fw-semibold" for="mediakit_library">Biblioteka</label>
                                    <select id="mediakit_library" name="library_id" class="form-select form-select-solid js-media-library" data-folder-target="mediakit_collection" required>
                                        @foreach($libraries as $item)
                                            <option value="{{ $item->getKey() }}" @selected($selectedLibrary === (string) $item->getKey())>{{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label fw-semibold" for="mediakit_collection">Folder</label>
                                    <select id="mediakit_collection" name="collection" class="form-select form-select-solid" data-selected="{{ $selectedCollection ?: $defaultCollection }}" required></select>
                                </div>
                                <div>
                                    <label class="form-label fw-semibold" for="mediakit_title">Tytuł (przy jednym pliku)</label>
                                    <input id="mediakit_title" type="text" name="title" class="form-control form-control-solid">
                                </div>
                                <div>
                                    <label class="form-label fw-semibold" for="mediakit_alt">Tekst alternatywny (przy jednej grafice)</label>
                                    <input id="mediakit_alt" type="text" name="alt" class="form-control form-control-solid">
                                </div>
                                <button type="submit" class="btn btn-primary">Dodaj do biblioteki</button>
                            </form>

                            <hr class="my-5">

                            <details class="mb-3">
                                <summary class="btn btn-sm btn-light-primary">Nowa biblioteka</summary>
                                <form method="post" action="{{ route($routePrefix . '.libraries.store') }}" class="d-flex flex-column gap-2 mt-3 js-media-create" data-create="library">
                                    @csrf
                                    <label for="mediakit_new_library" class="form-label fw-semibold">Nazwa biblioteki</label>
                                    <input id="mediakit_new_library" type="text" name="name" class="form-control form-control-sm" required>
                                    <button type="submit" class="btn btn-sm btn-primary">Utwórz bibliotekę</button>
                                </form>
                            </details>

                            <details>
                                <summary class="btn btn-sm btn-light-primary">Nowy folder</summary>
                                <form method="post" action="{{ route($routePrefix . '.folders.store') }}" class="d-flex flex-column gap-2 mt-3 js-media-create" data-create="folder">
                                    @csrf
                                    <label for="mediakit_folder_library" class="form-label fw-semibold">Biblioteka</label>
                                    <select id="mediakit_folder_library" name="library_id" class="form-select form-select-sm js-media-library" required>
                                        @foreach($libraries as $item)
                                            <option value="{{ $item->getKey() }}" @selected($selectedLibrary === (string) $item->getKey())>{{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                    <label for="mediakit_new_folder" class="form-label fw-semibold">Nazwa folderu</label>
                                    <input id="mediakit_new_folder" type="text" name="name" class="form-control form-control-sm" required>
                                    <button type="submit" class="btn btn-sm btn-primary">Utwórz folder</button>
                                </form>
                            </details>
                            <div id="media_dynamic_status" class="small mt-3" aria-live="polite"></div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-8">
                        <form id="media-bulk-form" method="post" action="{{ route($routePrefix . '.bulk.move') }}" class="rounded-3 border bg-light p-3 mb-4">
                            @csrf
                            <div class="d-flex flex-wrap align-items-end gap-3">
                                <label class="form-check mb-2">
                                    <input id="media_select_all" class="form-check-input" type="checkbox">
                                    <span class="form-check-label">Zaznacz stronę</span>
                                </label>
                                <div>
                                    <label for="media_bulk_library" class="form-label small fw-semibold mb-1">Przenieś do biblioteki</label>
                                    <select id="media_bulk_library" name="library_id" class="form-select form-select-sm js-media-library" data-folder-target="media_bulk_collection" required>
                                        @foreach($libraries as $item)
                                            <option value="{{ $item->getKey() }}" @selected($selectedLibrary === (string) $item->getKey())>{{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="media_bulk_collection" class="form-label small fw-semibold mb-1">Folder docelowy</label>
                                    <select id="media_bulk_collection" name="collection" class="form-select form-select-sm" data-selected="{{ $selectedCollection ?: $defaultCollection }}" required></select>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary js-media-bulk-button" disabled>Przenieś (<span class="js-media-selected-count">0</span>)</button>
                                <button type="submit" formaction="{{ route($routePrefix . '.bulk.destroy') }}" class="btn btn-sm btn-light-danger js-media-bulk-button" data-confirm="Usunąć wszystkie zaznaczone pliki? Operacja zostanie zablokowana, jeśli którykolwiek plik jest używany." disabled>
                                    Usuń (<span class="js-media-selected-count">0</span>)
                                </button>
                            </div>
                        </form>

                        <div class="row g-4">
                            @forelse($assets as $asset)
                                @php($title = (string) data_get($asset->meta, 'title', ''))
                                @php($alt = (string) data_get($asset->meta, 'alt', ''))
                                @php($isVideo = str_starts_with((string) $asset->original_mime, 'video/'))
                                @php($usages = $assetUsages[$asset->uuid] ?? [])
                                <div class="col-sm-6 col-xxl-4">
                                    <article class="card border-0 shadow-sm h-100">
                                        <div class="card-body p-4 d-flex flex-column">
                                            <label class="form-check mb-3">
                                                <input class="form-check-input js-media-select" type="checkbox" name="asset_uuids[]" value="{{ $asset->uuid }}" form="media-bulk-form">
                                                <span class="form-check-label">Zaznacz plik</span>
                                            </label>
                                            <div class="rounded-4 overflow-hidden border mb-4 bg-light d-flex align-items-center justify-content-center" style="min-height: 200px;">
                                                @if($isVideo)
                                                    <div class="text-center p-4">
                                                        <div class="fs-1 mb-2">🎬</div>
                                                        <div class="small text-muted">{{ strtoupper((string) $asset->original_ext) }}</div>
                                                    </div>
                                                @else
                                                    <img src="{{ $assetPreviewUrls->get((string) $asset->getKey()) }}"
                                                         alt="{{ $alt ?: ($title ?: 'Podgląd grafiki') }}"
                                                         class="img-fluid w-100"
                                                         style="height: 200px; object-fit: contain;"
                                                         loading="lazy"
                                                         decoding="async">
                                                @endif
                                            </div>

                                            <div class="d-flex flex-column gap-2 mb-4">
                                                <div class="fw-bold text-gray-900 text-break">{{ $title !== '' ? $title : basename($asset->original_path) }}</div>
                                                <div class="small text-muted">📁 {{ $asset->collection }} • {{ strtoupper((string) $asset->original_ext) }}</div>
                                                @if($alt !== '')
                                                    <div class="small text-gray-700">{{ $alt }}</div>
                                                @endif
                                                <div class="small text-muted">{{ number_format(((int) $asset->original_size) / 1024, 1) }} KB</div>
                                                <div class="small text-muted text-break">UUID: {{ $asset->uuid }}</div>
                                                <div>
                                                    @if($usages !== [])
                                                        <span class="badge badge-light-success">Używany: {{ count($usages) }}</span>
                                                    @else
                                                        <span class="badge badge-light-secondary">Nieprzypisany</span>
                                                    @endif
                                                </div>
                                                @if($usages !== [])
                                                    <div class="rounded-3 bg-light p-3 mt-1">
                                                        <div class="small fw-semibold mb-2">Przypisania</div>
                                                        <ul class="small mb-0 ps-4">
                                                            @foreach($usages as $usage)
                                                                <li class="mb-1">
                                                                    @if(!empty($usage['url']))
                                                                        <a href="{{ $usage['url'] }}" class="text-gray-800 text-hover-primary">{{ $usage['label'] }}</a>
                                                                    @else
                                                                        <span>{{ $usage['label'] }}</span>
                                                                    @endif
                                                                    @if(!empty($usage['location']))
                                                                        <span class="text-muted"> — {{ $usage['location'] }}</span>
                                                                    @endif
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                            </div>

                                            <details class="mb-4">
                                                <summary class="btn btn-light btn-sm">Edytuj dane</summary>
                                                <form method="post" action="{{ route($routePrefix . '.update', $asset->uuid) }}" class="d-flex flex-column gap-3 mt-3">
                                                    @csrf
                                                    @method('PUT')
                                                    <label for="asset_title_{{ $asset->uuid }}" class="visually-hidden">Tytuł techniczny</label>
                                                    <input id="asset_title_{{ $asset->uuid }}" type="text" name="title" value="{{ $title }}" class="form-control form-control-sm" placeholder="Tytuł techniczny">
                                                    <label for="asset_alt_{{ $asset->uuid }}" class="visually-hidden">Tekst alternatywny</label>
                                                    <input id="asset_alt_{{ $asset->uuid }}" type="text" name="alt" value="{{ $alt }}" class="form-control form-control-sm" placeholder="Tekst alternatywny">
                                                    <label for="asset_collection_{{ $asset->uuid }}" class="visually-hidden">Folder</label>
                                                    <select id="asset_collection_{{ $asset->uuid }}" name="collection" class="form-select form-select-sm js-media-asset-folder" required>
                                                        @foreach($folders as $folder)
                                                            <option value="{{ $folder['key'] }}" @selected($asset->collection === $folder['key'])>{{ $folder['name'] }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-primary btn-sm">Zapisz dane</button>
                                                </form>
                                            </details>

                                            <div class="mt-auto d-flex flex-wrap gap-2">
                                                @unless($isVideo)
                                                    <a href="{{ route('mediakit.media.show', [$asset->uuid, 'lg']) }}" target="_blank" rel="noopener" class="btn btn-light-primary btn-sm">Podgląd</a>
                                                @endunless
                                                <form method="post" action="{{ route($routePrefix . '.destroy', $asset->uuid) }}" onsubmit="return confirm('Delete this file from the media library?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-light-danger btn-sm" @disabled($usages !== []) title="{{ $usages !== [] ? 'Najpierw usuń przypisania tego pliku.' : '' }}">Usuń</button>
                                                </form>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="rounded-4 border border-dashed border-gray-300 px-5 py-8 text-center text-muted bg-light">
                                        Brak plików spełniających wybrane kryteria.
                                    </div>
                                </div>
                            @endforelse
                        </div>

                        @if($assets->hasPages())
                            <div class="mt-6">
                                {{ $assets->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const foldersByLibrary = @json($foldersByLibrary);
            const defaultCollection = @json($defaultCollection);
            const selectedLibrary = @json($selectedLibrary);
            const mediaIndexUrl = @json(route($routePrefix . '.index'));
            const activeFilters = @json($folderNavigationFilters);
            const status = document.getElementById('media_dynamic_status');

            const setStatus = (message, isError = false) => {
                if (!status) return;
                status.textContent = message;
                status.classList.toggle('text-danger', isError);
                status.classList.toggle('text-success', !isError && message !== '');
            };

            const updateFolderSelect = (select, libraryId, preferred = '') => {
                if (!select) return;
                const folders = foldersByLibrary[libraryId] || [];
                const selected = preferred || select.dataset.selected || defaultCollection;
                select.replaceChildren();

                if (folders.length === 0) {
                    folders.push({key: defaultCollection, name: defaultCollection, count: 0});
                }

                folders.forEach((folder) => {
                    const option = document.createElement('option');
                    option.value = folder.key;
                    option.textContent = folder.name;
                    option.selected = folder.key === selected;
                    select.append(option);
                });
                select.dataset.selected = '';
            };

            document.querySelectorAll('.js-media-library[data-folder-target]').forEach((librarySelect) => {
                const folderSelect = document.getElementById(librarySelect.dataset.folderTarget);
                updateFolderSelect(folderSelect, librarySelect.value);
                librarySelect.addEventListener('change', () => updateFolderSelect(folderSelect, librarySelect.value));
            });

            document.getElementById('media_filter_library')?.addEventListener('change', (event) => {
                const collection = event.target.form?.querySelector('input[name="collection"]');
                if (collection instanceof HTMLInputElement) collection.value = '';
            });

            const appendLibraryOption = (library) => {
                document.querySelectorAll('.js-media-library, [data-media-filter-library]').forEach((select) => {
                    if ([...select.options].some((option) => option.value === String(library.id))) return;
                    select.add(new Option(library.name, library.id));
                });
                foldersByLibrary[library.id] ||= [];
            };

            const folderUrl = (folder) => {
                const url = new URL(mediaIndexUrl, window.location.origin);
                url.searchParams.set('library', folder.library_id);
                url.searchParams.set('collection', folder.key);
                Object.entries(activeFilters).forEach(([key, value]) => {
                    if (value !== null && value !== '') url.searchParams.set(key, String(value));
                });

                return url.toString();
            };

            const appendFolderTile = (folder) => {
                if (String(folder.library_id) !== selectedLibrary) return;

                const grid = document.querySelector('[data-media-folder-grid]');
                const alreadyRendered = [...(grid?.querySelectorAll('[data-media-folder-card]') || [])]
                    .some((card) => card.dataset.mediaFolderCard === folder.key);
                if (!grid || alreadyRendered) return;

                const column = document.createElement('div');
                column.className = 'col-6 col-md-4 col-xl-3';
                column.dataset.mediaFolderCard = folder.key;

                const link = document.createElement('a');
                link.href = folderUrl(folder);
                link.className = 'd-flex align-items-center gap-3 rounded-3 border p-3 text-gray-800 text-hover-primary h-100 bg-light';

                const icon = document.createElement('span');
                icon.className = 'fs-2';
                icon.setAttribute('aria-hidden', 'true');
                icon.textContent = '📁';

                const content = document.createElement('span');
                content.className = 'min-w-0';
                const name = document.createElement('span');
                name.className = 'd-block fw-semibold text-truncate';
                name.textContent = folder.name;
                const count = document.createElement('span');
                count.className = 'd-block small text-muted';
                count.textContent = `${folder.count ?? folder.assets_count ?? 0} plików`;

                content.append(name, count);
                link.append(icon, content);
                column.append(link);
                grid.append(column);

                document.querySelectorAll('select.js-media-asset-folder').forEach((select) => {
                    if (![...select.options].some((option) => option.value === folder.key)) {
                        select.add(new Option(folder.name, folder.key));
                    }
                });
            };

            document.querySelectorAll('.js-media-create').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const button = form.querySelector('button[type="submit"]');
                    button.disabled = true;
                    setStatus('Zapisywanie…');

                    try {
                        const response = await fetch(form.action, {
                            method: form.method,
                            headers: {'Accept': 'application/json'},
                            body: new FormData(form),
                        });
                        const payload = await response.json();

                        if (!response.ok) {
                            const errors = Object.values(payload.errors || {}).flat();
                            throw new Error(errors[0] || payload.message || 'Nie udało się zapisać zmian.');
                        }

                        if (form.dataset.create === 'library') {
                            appendLibraryOption(payload.library);
                            const uploadLibrary = document.getElementById('mediakit_library');
                            uploadLibrary.value = payload.library.id;
                            uploadLibrary.dispatchEvent(new Event('change'));
                            document.getElementById('mediakit_folder_library').value = payload.library.id;
                            setStatus(`Utworzono bibliotekę „${payload.library.name}”.`);
                        } else {
                            const folder = payload.folder;
                            foldersByLibrary[folder.library_id] ||= [];
                            const existingFolder = foldersByLibrary[folder.library_id]
                                .find((item) => item.key === folder.key);
                            if (!existingFolder) foldersByLibrary[folder.library_id].push(folder);
                            const uploadLibrary = document.getElementById('mediakit_library');
                            uploadLibrary.value = folder.library_id;
                            updateFolderSelect(
                                document.getElementById(uploadLibrary.dataset.folderTarget),
                                folder.library_id,
                                folder.key,
                            );
                            document.querySelectorAll('.js-media-library[data-folder-target]').forEach((librarySelect) => {
                                if (librarySelect.value !== String(folder.library_id)) return;
                                const folderSelect = document.getElementById(librarySelect.dataset.folderTarget);
                                updateFolderSelect(folderSelect, folder.library_id, folder.key);
                            });
                            appendFolderTile(folder);
                            setStatus(`Utworzono folder „${folder.name}”.`);
                        }

                        form.reset();
                    } catch (error) {
                        setStatus(error.message || 'Nie udało się zapisać zmian.', true);
                    } finally {
                        button.disabled = false;
                    }
                });
            });

            const checkboxes = [...document.querySelectorAll('.js-media-select')];
            const selectAll = document.getElementById('media_select_all');
            const bulkButtons = document.querySelectorAll('.js-media-bulk-button');
            const countLabels = document.querySelectorAll('.js-media-selected-count');
            const refreshBulkState = () => {
                const count = checkboxes.filter((checkbox) => checkbox.checked).length;
                countLabels.forEach((label) => label.textContent = String(count));
                bulkButtons.forEach((button) => button.disabled = count === 0);
                if (selectAll) {
                    selectAll.checked = count > 0 && count === checkboxes.length;
                    selectAll.indeterminate = count > 0 && count < checkboxes.length;
                }
            };

            checkboxes.forEach((checkbox) => checkbox.addEventListener('change', refreshBulkState));
            selectAll?.addEventListener('change', () => {
                checkboxes.forEach((checkbox) => checkbox.checked = selectAll.checked);
                refreshBulkState();
            });
            document.getElementById('media-bulk-form')?.addEventListener('submit', (event) => {
                const message = event.submitter?.dataset.confirm;
                if (message && !window.confirm(message)) event.preventDefault();
            });
        })();
    </script>
@endsection
