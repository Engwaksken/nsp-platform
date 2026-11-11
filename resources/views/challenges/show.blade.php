@extends('layouts.browse', ['pageTitle' => $version->title, 'metaDescription' => \Illuminate\Support\Str::limit($version->problem_description, 155), 'canonical' => route('challenges.show', $challenge)])

@if ($version->visibility === 'members')
    @push('head')<meta name="robots" content="noindex">@endpush
@endif

@section('content')
    <article class="container section narrow">
        <p class="meta"><a href="{{ route('challenges.index') }}">Challenge Exchange</a> · {{ config('challenges.categories')[$version->category] ?? 'Challenge' }}</p>
        <h1>{{ $version->title }}</h1>
        @include('partials.flash')

        <dl>
            <dt>Priority</dt><dd>{{ config('challenges.priorities')[$version->priority] ?? $version->priority }}</dd>
            @if ($version->sector)<dt>Sector</dt><dd>{{ $version->sector }}</dd>@endif
            @if ($version->field('geography') || $version->country || $version->region)
                <dt>Location</dt><dd>{{ collect([$version->field('geography'), $version->region, \App\Models\Registry\Startup::COUNTRIES[$version->country] ?? null])->filter()->implode(', ') }}</dd>
            @endif
            @if ($version->issuer_name || $version->issuer_type)
                <dt>Issued by</dt><dd>{{ $version->issuer_name ?: 'Not named' }}@if ($version->issuer_type) ({{ config('challenges.issuer_types')[$version->issuer_type] ?? $version->issuer_type }})@endif</dd>
            @endif
            @if ($version->field('tags'))<dt>Tags</dt><dd><ul class="tags">@foreach ($version->field('tags') as $tag)<li class="tag">{{ $tag }}</li>@endforeach</ul></dd>@endif
        </dl>

        <h2>The problem</h2>
        <div class="prose">{!! $html('problem_description') !!}</div>
        @foreach (['market_opportunity' => 'Market opportunity', 'existing_solutions' => 'Existing solutions', 'policy_considerations' => 'Policy considerations'] as $field => $heading)
            @if ($version->field($field))
                <h2>{{ $heading }}</h2>
                <div class="prose">{!! $html($field) !!}</div>
            @endif
        @endforeach

        @if ($version->field('research_references'))
            <h2>Research references</h2>
            <ul>
                @foreach ($version->field('research_references') as $ref)
                    <li>@if (!empty($ref['url']))<a href="{{ $ref['url'] }}" rel="noopener nofollow" target="_blank">{{ $ref['title'] }}</a>@else{{ $ref['title'] }}@endif
                        @if (!empty($ref['accessed']))<span class="meta">(accessed {{ \Illuminate\Support\Carbon::parse($ref['accessed'])->format('j M Y') }})</span>@endif</li>
                @endforeach
            </ul>
        @endif

        <section aria-labelledby="evidence-heading">
            <h2 id="evidence-heading">Supporting evidence</h2>
            @if ($evidence->isEmpty())
                <p class="hint">No supporting evidence was attached.</p>
            @else
                <ul>
                    @foreach ($evidence as $item)
                        <li>
                            @if ($item->url)<a href="{{ $item->url }}" rel="noopener nofollow" target="_blank">{{ $item->source_title }}</a>@else{{ $item->source_title }} <span class="meta">(document held privately by Hive)</span>@endif
                            <span class="meta">· {{ config('challenges.evidence_types')[$item->evidence_type] ?? $item->evidence_type }}</span><br>
                            @if ($item->validated && $item->validated_at)
                                <span class="badge badge-on">Evidence reviewed on {{ $item->validated_at->format('j M Y') }} by Hive</span>
                            @else
                                <span class="badge badge-off">Not yet reviewed</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($links)
            <section aria-labelledby="related-heading">
                <h2 id="related-heading">Related</h2>
                @foreach ($links as $label => $items)
                    <p><strong>{{ $label }}:</strong>
                        @foreach ($items as $item)
                            @if ($item['url'])<a class="tag" href="{{ $item['url'] }}">{{ $item['key'] }}</a>@else<span class="tag">{{ $item['key'] }}</span>@endif
                        @endforeach
                    </p>
                @endforeach
            </section>
        @endif

        <aside class="panel" aria-labelledby="provenance-heading">
            <h2 id="provenance-heading" class="h3">Provenance</h2>
            <p class="meta">Version {{ $version->version }} · published {{ $version->published_at?->format('j M Y') }}
                @if ($challenge->last_reviewed_at) · last reviewed {{ $challenge->last_reviewed_at->format('j M Y') }}@endif</p>
            <p class="hint">Publication means Hive reviewers checked this listing for clarity and relevance. It does not mean every claim has been validated: only evidence marked “Evidence reviewed” has been checked.</p>
        </aside>

        <div class="actions" style="margin-top:1rem">
            @auth
                <form method="post" action="{{ route('challenges.bookmark', $challenge) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">{{ $bookmarked ? 'Remove from saved' : 'Save challenge' }}</button>
                </form>
            @else
                <a class="btn btn-secondary" href="{{ route('login') }}">Sign in to save or propose a solution</a>
            @endauth
        </div>

        @auth
            <section class="panel" aria-labelledby="propose-heading" style="margin-top:1.5rem">
                <h2 id="propose-heading">Express interest or propose a solution</h2>
                @if ($proposal && $proposal->status !== 'declined')
                    <p>You sent a proposal on {{ $proposal->created_at->format('j M Y') }}. Status: <strong>{{ \App\Models\Challenges\ChallengeSolutionSubmission::STATUSES[$proposal->status] }}</strong>.
                        <a href="{{ route('challenges.proposals.show', $proposal) }}">View your proposal</a></p>
                @else
                    <form method="post" action="{{ route('challenges.propose', $challenge) }}">
                        @csrf
                        @if ($memberships->isNotEmpty())
                            @include('partials.field', ['name' => 'startup_uuid', 'label' => 'On behalf of', 'type' => 'select', 'placeholder' => 'Just me',
                                'options' => $memberships->mapWithKeys(fn ($m) => [$m->startup_uuid => $m->startup->display_name])->all()])
                        @endif
                        @include('partials.field', ['name' => 'summary', 'label' => 'Summary of your interest or solution', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'hint' => '20 to 1,000 characters.'])
                        @include('partials.field', ['name' => 'approach', 'label' => 'Approach (optional)', 'type' => 'textarea', 'rows' => 5, 'hint' => 'How you would solve it, what you have already built, and what you need.'])
                        <div class="form-group">
                            <label class="checkbox"><input type="checkbox" name="confirm" value="1" required> <span>I understand that only Hive reviewers, me and my startup team can see this proposal. Do not include confidential information you are not allowed to share.</span></label>
                            @error('confirm')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Send proposal</button>
                    </form>
                @endif
            </section>
        @endauth
    </article>
@endsection
