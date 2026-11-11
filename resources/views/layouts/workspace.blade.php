{{--
    Startup workspace pages: the member app shell (layouts/app), whose sidebar shows the
    "My startup" and "Grow" groups for $startup. Pages put their content in @section('workspace').
    Without a saved startup (the "register a startup" form) the sidebar falls back to the active startup.
--}}
@extends('layouts.app')

@section('content')
    @yield('workspace')
@endsection
