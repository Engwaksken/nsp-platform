@extends('layouts.public', ['pageTitle' => 'Frequently asked questions', 'canonical' => route('faqs')])

@section('content')
    @php
        $icons = [
            'about' => 'fa-circle-info', 'account' => 'fa-user', 'startup' => 'fa-rocket', 'team' => 'fa-rocket', 'privacy' => 'fa-shield-halved',
            'sharing' => 'fa-shield-halved', 'growth' => 'fa-seedling', 'funding' => 'fa-hand-holding-dollar', 'investor' => 'fa-hand-holding-dollar',
            'expert' => 'fa-briefcase', 'job' => 'fa-briefcase', 'opportunit' => 'fa-bullhorn', 'organization' => 'fa-building', 'help' => 'fa-life-ring',
        ];
        $iconFor = function (string $topic) use ($icons): string {
            foreach ($icons as $word => $icon) {
                if (str_contains(strtolower($topic), $word)) {
                    return $icon;
                }
            }

            return 'fa-circle-question';
        };
    @endphp
    <div class="container section">
        <header class="faq-head">
            <h1>Frequently asked questions</h1>
            <p class="lead-muted">Answers about accounts, startups, data sharing, funding and the other services on {{ $brand['site_name'] }}.</p>
            @if ($groups->count() > 1)
                <nav class="faq-topics" aria-label="FAQ topics">
                    @foreach ($groups as $topic => $faqs)
                        <a href="#faq-{{ \Illuminate\Support\Str::slug($topic) }}"><i class="fas {{ $iconFor($topic) }}" aria-hidden="true"></i> {{ $topic }}</a>
                    @endforeach
                </nav>
            @endif
        </header>

        @if ($groups->isEmpty())
            <p class="empty">No questions have been published yet. <a href="{{ route('contact') }}">Contact us</a> with your question.</p>
        @else
            <div class="faq-grid">
                @foreach ($groups as $topic => $faqs)
                    <section class="faq-group" id="faq-{{ \Illuminate\Support\Str::slug($topic) }}" aria-labelledby="faq-{{ \Illuminate\Support\Str::slug($topic) }}-title">
                        <header class="faq-group-head">
                            <span class="faq-group-icon" aria-hidden="true"><i class="fas {{ $iconFor($topic) }}"></i></span>
                            <h2 id="faq-{{ \Illuminate\Support\Str::slug($topic) }}-title">{{ $topic }}</h2>
                            <span class="faq-count">{{ $faqs->count() }}</span>
                        </header>
                        @foreach ($faqs as $faq)
                            <details class="faq"><summary>{{ $faq->question }}</summary>{!! \App\Support\Markdown::render($faq->answer) !!}</details>
                        @endforeach
                    </section>
                @endforeach
            </div>

            <aside class="faq-more">
                <span class="faq-group-icon" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                <div>
                    <h2>Still have a question?</h2>
                    <p>Send us a message and the right team will reply.</p>
                </div>
                <a class="btn btn-primary" href="{{ route('contact') }}">Contact us</a>
            </aside>
        @endif
    </div>
@endsection
