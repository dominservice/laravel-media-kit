@php($mode = config('media-kit.admin.layout.mode', 'package'))
@php($layoutView = config('media-kit.admin.layout.view', 'mediakit::layouts.bootstrap'))
@php($layoutSection = config('media-kit.admin.layout.section', 'content'))
@php($component = config('media-kit.admin.layout.component', 'admin-layout'))

@if($mode === 'component')
    <x-dynamic-component :component="$component">
        @yield('content')
    </x-dynamic-component>
@else
    @extends($layoutView)

    @section($layoutSection)
        @yield('content')
    @endsection
@endif
