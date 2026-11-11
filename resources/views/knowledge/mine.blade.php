@extends('layouts.app', ['pageTitle' => 'Saved Knowledge resources', 'metaDescription' => 'Your saved and recently viewed Knowledge Hub resources.'])

@section('content')
    <div class="container section">
        <div class="page-head"><h1>Saved and recent resources</h1><a class="btn btn-secondary" href="{{ route('knowledge.index') }}">Browse Knowledge Hub</a></div>
        @if ($recommendations->isNotEmpty())<section class="panel"><h2>Recommended for you</h2><ul>@foreach ($recommendations as $row)<li><a href="{{ route('knowledge.show', $row['resource']) }}">{{ $row['resource']->title }}</a> — {{ implode(' · ', $row['reasons']) }}</li>@endforeach</ul></section>@endif
        <section><h2>Saved</h2><div class="grid">@forelse ($bookmarks as $bookmark)<article class="card"><h3><a href="{{ route('knowledge.show', $bookmark->resource) }}">{{ $bookmark->resource->title }}</a></h3><p>{{ $bookmark->resource->summary }}</p></article>@empty<p class="empty">You have no saved resources that are currently available to you.</p>@endforelse</div></section>
        <section><h2>Recently viewed</h2><ul>@forelse ($recent as $view)<li><a href="{{ route('knowledge.show', $view->resource) }}">{{ $view->resource->title }}</a> · {{ $view->viewed_at->format('j M Y') }}</li>@empty<li>No recent resources.</li>@endforelse</ul></section>
    </div>
@endsection
