@extends('mediakit::layouts.admin')

@php($routePrefix = rtrim(config('media-kit.admin.route_name_prefix', 'admin.media.'), '.'))
@php($uploadAccept = (string) config('media-kit.admin.library.accept', 'image/*,video/*'))

@section('content')
    <div class="d-flex flex-column gap-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-6">
                <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 mb-5">
                    <div>
                        <h1 class="fs-2 fw-bold mb-1">Media Library</h1>
                        <div class="text-muted">Reusable image and video assets for CMS blocks and pages.</div>
                    </div>
                    <form method="get" action="{{ route($routePrefix . '.index') }}" class="d-flex flex-column flex-md-row gap-3 w-100 w-xl-auto">
                        <input type="text" name="q" value="{{ $search }}" class="form-control form-control-solid" placeholder="Search by collection, path, title or alt">
                        <select name="collection" class="form-select form-select-solid">
                            <option value="">All collections</option>
                            @foreach($collections as $collection)
                                <option value="{{ $collection }}" @selected($selectedCollection === $collection)>{{ $collection }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-light-primary">Filter</button>
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

                <div class="row g-5">
                    <div class="col-12 col-xl-4">
                        <div class="rounded-4 border border-dashed border-gray-300 p-5 h-100 bg-light">
                            <h2 class="h4 mb-2">Upload asset</h2>
                            <p class="text-muted mb-4">The file is added to the shared media library and can be reused across CMS content.</p>

                            <form method="post" action="{{ route($routePrefix . '.store') }}" enctype="multipart/form-data" class="d-flex flex-column gap-4">
                                @csrf
                                <div>
                                    <label class="form-label fw-semibold" for="mediakit_file">File</label>
                                    <input id="mediakit_file" type="file" name="file" class="form-control form-control-solid" accept="{{ $uploadAccept }}" required>
                                </div>
                                <div>
                                    <label class="form-label fw-semibold" for="mediakit_title">Technical title</label>
                                    <input id="mediakit_title" type="text" name="title" class="form-control form-control-solid">
                                </div>
                                <div>
                                    <label class="form-label fw-semibold" for="mediakit_alt">Alt / accessibility</label>
                                    <input id="mediakit_alt" type="text" name="alt" class="form-control form-control-solid">
                                </div>
                                <div>
                                    <label class="form-label fw-semibold" for="mediakit_collection">Collection</label>
                                    <input id="mediakit_collection" type="text" name="collection" class="form-control form-control-solid" value="{{ config('media-kit.admin.library.default_collection', 'library') }}">
                                </div>
                                <button type="submit" class="btn btn-primary">Add to library</button>
                            </form>
                        </div>
                    </div>

                    <div class="col-12 col-xl-8">
                        <div class="row g-4">
                            @forelse($assets as $asset)
                                @php($title = (string) data_get($asset->meta, 'title', ''))
                                @php($alt = (string) data_get($asset->meta, 'alt', ''))
                                @php($isVideo = str_starts_with((string) $asset->original_mime, 'video/'))
                                @php($usages = $assetUsages[$asset->uuid] ?? [])
                                <div class="col-sm-6 col-xxl-4">
                                    <article class="card border-0 shadow-sm h-100">
                                        <div class="card-body p-4 d-flex flex-column">
                                            <div class="rounded-4 overflow-hidden border mb-4 bg-light d-flex align-items-center justify-content-center" style="min-height: 200px;">
                                                @if($isVideo)
                                                    <div class="text-center p-4">
                                                        <div class="fs-1 mb-2">🎬</div>
                                                        <div class="small text-muted">{{ strtoupper((string) $asset->original_ext) }}</div>
                                                    </div>
                                                @else
                                                    <x-media-picture :asset="$asset" variant="sm" :alt="$alt ?: ($title ?: 'Preview image')" class="img-fluid w-100" />
                                                @endif
                                            </div>

                                            <div class="d-flex flex-column gap-2 mb-4">
                                                <div class="fw-bold text-gray-900 text-break">{{ $title !== '' ? $title : basename($asset->original_path) }}</div>
                                                <div class="small text-muted">{{ $asset->collection }} • {{ strtoupper((string) $asset->original_ext) }}</div>
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
                                                    <input type="text" name="title" value="{{ $title }}" class="form-control form-control-sm" placeholder="Tytuł techniczny">
                                                    <input type="text" name="alt" value="{{ $alt }}" class="form-control form-control-sm" placeholder="Tekst alternatywny">
                                                    <input type="text" name="collection" value="{{ $asset->collection }}" class="form-control form-control-sm" required placeholder="Kolekcja">
                                                    <button type="submit" class="btn btn-primary btn-sm">Zapisz dane</button>
                                                </form>
                                            </details>

                                            <div class="mt-auto d-flex flex-wrap gap-2">
                                                @unless($isVideo)
                                                    <a href="{{ route('mediakit.media.show', [$asset->uuid, 'lg']) }}" target="_blank" class="btn btn-light-primary btn-sm">Preview</a>
                                                @endunless
                                                <form method="post" action="{{ route($routePrefix . '.destroy', $asset->uuid) }}" onsubmit="return confirm('Delete this file from the media library?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-light-danger btn-sm" @disabled($usages !== []) title="{{ $usages !== [] ? 'Najpierw usuń przypisania tego pliku.' : '' }}">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="rounded-4 border border-dashed border-gray-300 px-5 py-8 text-center text-muted bg-light">
                                        The media library is empty.
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
@endsection
