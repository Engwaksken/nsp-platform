@extends('layouts.public', ['pageTitle' => 'Frequently asked questions', 'canonical' => route('faqs')])

{{--
    FAQs: a topic list (sticky beside the questions on wide screens, a row of chips on phones) and one column of
    questions grouped under each topic, in the order set in Admin → FAQs. With JavaScript a search box filters the
    questions (js/nsp.js, [data-faq-search]); without it every question is listed and the topic links still jump.
--}}
@section('content')
    @php
        $icons = [
            'about' => 'fa-circle-info', 'general' => 'fa-circle-info', 'account' => 'fa-user', 'startup' => 'fa-rocket', 'team' => 'fa-users',
            'privacy' => 'fa-shield-halved', 'sharing' => 'fa-shield-halved', 'data' => 'fa-shield-halved', 'growth' => 'fa-seedling',
            'funding' => 'fa-hand-holding-dollar', 'investor' => 'fa-hand-holding-dollar', 'expert' => 'fa-briefcase', 'job' => 'fa-briefcase',
            'opportunit' => 'fa-bullhorn', 'organization' => 'fa-building', 'organisation' => 'fa-building', 'help' => 'fa-life-ring',
        ];
        $iconFor = function (string $topic) use ($icons): string {
            foreach ($icons as $word => $icon) {
                if (str_contains(strtolower($topic), $word)) {
                    return $icon;
                }
            }

            return 'fa-circle-question';
        };
        $slug = fn (string $topic) => 'faq-'.(\Illuminate\Support\Str::slug($topic) ?: 'general');
        $total = $groups->sum(fn ($faqs) => $faqs->count());
    @endphp

    <div class="faq-hero">
        <div class="container">
            <h1>Frequently asked questions</h1>
            <p class="lead-muted">Answers about accounts, startups, data sharing, funding and the other services on {{ $brand['site_name'] }}.</p>
            @if ($total > 0)
                <div class="faq-search" data-faq-search hidden>
                    <label for="faq-search-input" class="visually-hidden">Search the questions</label>
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="faq-search-input" placeholder="Search {{ $total }} {{ \Illuminate\Support\Str::plural('question', $total) }}…" autocomplete="off" data-faq-search-input>
                </div>
            @endif
        </div>
    </div>

    <div class="container section faq-page">
        @if ($groups->isEmpty())
            <p class="empty">No questions have been published yet. <a href="{{ route('contact') }}">Contact us</a> with your question.</p>
        @else
            <div class="faq-layout {{ $groups->count() > 1 ? 'has-topics' : '' }}">
                @if ($groups->count() > 1)
                    <nav class="faq-toc" aria-label="FAQ topics">
                        <h2 class="faq-toc-title">Topics</h2>
                        <ul>
                            @foreach ($groups as $topic => $faqs)
                                <li><a href="#{{ $slug($topic) }}" data-faq-topic-link>
                                    <i class="fas {{ $iconFor($topic) }}" aria-hidden="true"></i>
                                    <span>{{ $topic }}</span>
                                    <span class="faq-toc-count" aria-label="{{ $faqs->count() }} {{ \Illuminate\Support\Str::plural('question', $faqs->count()) }}">{{ $faqs->count() }}</span>
                                </a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                <div class="faq-list">
                    <p class="faq-no-results" data-faq-empty hidden role="status">No questions match your search. Try other words, or <a href="{{ route('contact') }}">ask us directly</a>.</p>
                    <p class="visually-hidden" role="status" aria-live="polite" data-faq-status></p>
                    @foreach ($groups as $topic => $faqs)
                        <section class="faq-section" id="{{ $slug($topic) }}" aria-labelledby="{{ $slug($topic) }}-title" data-faq-section>
                            <h2 class="faq-section-title" id="{{ $slug($topic) }}-title">
                                <span class="faq-section-icon" aria-hidden="true"><i class="fas {{ $iconFor($topic) }}"></i></span>
                                {{ $topic }}
                            </h2>
                            <div class="faq-items">
                                @foreach ($faqs as $faq)
                                    <details class="faq" id="faq-q-{{ $faq->id }}" data-faq-item>
                                        <summary>{{ $faq->question }}</summary>
                                        <div class="faq-answer prose">{!! \App\Support\Markdown::render($faq->answer) !!}</div>
                                    </details>
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                    <aside class="faq-more">
                        <span class="faq-section-icon" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                        <div>
                            <h2>Still have a question?</h2>
                            <p>Send us a message and the right team will reply.</p>
                        </div>
                        <a class="btn btn-primary" href="{{ route('contact') }}">Contact us</a>
                    </aside>
                </div>
            </div>
        @endif
    </div>
@endsection
