{{--
    Startup workspace: the public header and footer, with a sidebar for one startup.
    Pages extend this layout with $startup set and put their content in @section('workspace').
    Without a saved startup (the "register a startup" form) the page shows without the sidebar.
--}}
@extends('layouts.public')

@section('content')
    @if (isset($startup) && $startup->exists)
        <div class="workspace-shell">
            <button type="button" class="btn btn-secondary btn-sm workspace-sidebar-toggle" data-toggle-target="workspace-sidebar" aria-expanded="false" aria-controls="workspace-sidebar">
                Sections · {{ $startup->display_name }}
            </button>
            <aside class="workspace-sidebar" id="workspace-sidebar">
                @include('workspace.startups.sidebar')
            </aside>
            <div class="workspace-main">
                @yield('workspace')
            </div>
        </div>
    @else
        @yield('workspace')
    @endif
@endsection
