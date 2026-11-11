@extends('layouts.browse', ['pageTitle' => 'Knowledge Hub', 'metaDescription' => 'Practical, research and market resources for founders and the wider ecosystem.'])

@section('content')
    <div class="container section">
        <div class="page-head"><h1>Knowledge Hub</h1>@auth<a class="btn btn-secondary" href="{{ route('knowledge.mine') }}">Saved and recent</a>@endauth</div>
        <p>Browse resources with their source, licence and access conditions. Recommendations are suggestions, not a substitute for professional advice.</p>
        @include('partials.stats-summary', ['label' => 'Knowledge Hub at a glance', 'stats' => \App\Support\PublicStats::knowledge()])

        <form method="get" action="{{ route('knowledge.index') }}" class="filters" role="search" aria-label="Search Knowledge Hub">
            <div class="form-group"><label for="knowledge-q">Search resources</label><input id="knowledge-q" name="q" value="{{ $q }}" maxlength="100"></div>
            <div class="form-group"><label for="knowledge-type">Type</label><select id="knowledge-type" name="type"><option value="">All types</option>@foreach (config('knowledge.resource_types') as $key => $label)<option value="{{ $key }}" @selected(($filters['type'] ?? null) === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="form-group"><label for="knowledge-category">Category</label><select id="knowledge-category" name="category"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ str_repeat('— ', $category->depth) }}{{ $category->name }}</option>@endforeach</select></div>
            <div class="form-group"><label for="knowledge-audience">Audience</label><select id="knowledge-audience" name="audience"><option value="">All audiences</option>@foreach (config('knowledge.audiences') as $key => $label)<option value="{{ $key }}" @selected(($filters['audience'] ?? null) === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="form-group"><label for="knowledge-stage">Venture stage</label><select id="knowledge-stage" name="stage"><option value="">All stages</option>@foreach (config('knowledge.stages') as $key => $label)<option value="{{ $key }}" @selected(($filters['stage'] ?? null) === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="form-group"><label for="knowledge-tag">Tag</label><select id="knowledge-tag" name="tag"><option value="">All tags</option>@foreach ($tags as $tag)<option value="{{ $tag->slug }}" @selected(($filters['tag'] ?? null) === $tag->slug)>{{ $tag->name }}</option>@endforeach</select></div>
            <button class="btn btn-primary" type="submit">Search</button>
        </form>
        @auth
            @if ($recommendations->isNotEmpty())<section class="panel"><h2>Recommended for you</h2><ul>@foreach ($recommendations as $row)<li><a href="{{ route('knowledge.show', $row['resource']) }}">{{ $row['resource']->title }}</a> — {{ implode(' · ', $row['reasons']) }}</li>@endforeach</ul></section>@endif
        @endauth
        <p class="meta">Search method: {{ ucfirst($engine) }}. Only resources available to you are included.</p>
        <div class="card-grid">
            @forelse ($resources as $resource)
                <article class="card"><p class="meta">{{ config('knowledge.resource_types.'.$resource->resource_type, $resource->resource_type) }} @if ($resource->category) · {{ $resource->category->name }}@endif</p>
                    <h2><a href="{{ route('knowledge.show', $resource) }}">{{ $resource->title }}</a></h2>
                    @if ($resource->summary)<p>{{ $resource->summary }}</p>@endif
                    <p class="meta">{{ $resource->source ?: 'Source not recorded' }} · {{ config('knowledge.licenses.'.$resource->license.'.label', $resource->license) }}</p>
                    @if (in_array($resource->id, $bookmarked, true))<span class="badge badge-on">Saved</span>@endif
                </article>
            @empty<p class="empty">No resources match your search and access level.</p>@endforelse
        </div>
        {{ $resources->links() }}
    </div>
@endsection
