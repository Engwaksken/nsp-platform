@extends('layouts.browse', ['pageTitle' => $resource->title, 'metaDescription' => \Illuminate\Support\Str::limit($resource->summary ?: strip_tags($bodyHtml), 155)])

@section('content')
    <article class="container section narrow">
        <p class="meta"><a href="{{ route('knowledge.index') }}">Knowledge Hub</a> @if ($resource->category) · {{ $resource->category->name }}@endif</p>
        <h1>{{ $resource->title }}</h1>
        @include('partials.flash')
        @if ($resource->summary)<p class="lead">{{ $resource->summary }}</p>@endif
        <dl>
            <dt>Resource type</dt><dd>{{ $resource->typeLabel() }}</dd>
            <dt>Source</dt><dd>{{ $resource->source ?: 'Not recorded' }}</dd>
            @if ($resource->author)<dt>Author</dt><dd>{{ $resource->author }}</dd>@endif
            @if ($resource->publisher)<dt>Publisher</dt><dd>{{ $resource->publisher }}</dd>@endif
            @if ($resource->publication_date)<dt>Publication date</dt><dd>{{ $resource->publication_date->format('j M Y') }}</dd>@endif
            <dt>Licence</dt><dd>{{ $resource->licenseLabel() }}</dd>
            <dt>Published</dt><dd>{{ $resource->published_at->format('j M Y') }} · version {{ $resource->current_version ?: 'not recorded' }}</dd>
            <dt>Access</dt><dd>{{ $resource->visibilityLabel() }}</dd>
            @if ($resource->isStale())<dt>Editorial note</dt><dd>This resource is due for review and may need updating.</dd>@endif
        </dl>
        @if ($reasons)<p class="alert alert-info">{{ implode(' · ', $reasons) }}</p>@endif
        @if ($bodyHtml)<div class="prose">{!! $bodyHtml !!}</div>@endif
        <div class="actions">
            @if ($resource->hasFile() || $resource->external_url)<a class="btn btn-primary" href="{{ route('knowledge.download', $resource) }}">{{ $resource->hasFile() ? 'Download resource' : 'Open source link' }}</a>@endif
            @auth<form method="post" action="{{ route('knowledge.bookmark', $resource) }}">@csrf<button class="btn btn-secondary" type="submit">{{ $isBookmarked ? 'Remove saved resource' : 'Save resource' }}</button></form>@endauth
        </div>
        @if ($related)<section class="panel"><h2>Related resources</h2><ul>@foreach ($related as $row)<li><a href="{{ route('knowledge.show', $row['resource']) }}">{{ $row['resource']->title }}</a> ({{ $row['why'] }})</li>@endforeach</ul></section>@endif
    </article>
@endsection
