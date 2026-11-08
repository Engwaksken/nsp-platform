{{-- One page-builder section. $block['html'] is Markdown rendered with raw HTML escaped (App\Support\Markdown). --}}
@php($headingTag = ($first ?? false) && $block['type'] === 'hero' ? 'h1' : 'h2')
@switch($block['type'])
    @case('hero')
        <section class="hero">
            <div class="container">
                <{{ $headingTag }}>{{ $block['heading'] ?: $pageTitle }}</{{ $headingTag }}>
                @if ($block['html'])<div class="lead">{!! $block['html'] !!}</div>@endif
                @if ($block['link_url'] && $block['link_label'])
                    <p><a class="btn btn-primary" href="{{ $block['link_url'] }}">{{ $block['link_label'] }}</a></p>
                @endif
            </div>
        </section>
        @break

    @case('text')
        <section class="section">
            <div class="container prose">
                @if ($block['heading'])<h2>{{ $block['heading'] }}</h2>@endif
                {!! $block['html'] !!}
                @if ($block['link_url'] && $block['link_label'])
                    <p><a href="{{ $block['link_url'] }}">{{ $block['link_label'] }}</a></p>
                @endif
            </div>
        </section>
        @break

    @case('stats')
        <section class="section section-alt">
            <div class="container">
                @if ($block['heading'])<h2>{{ $block['heading'] }}</h2>@endif
                {!! $block['html'] !!}
                <dl class="stats">
                    @foreach ($block['data'] as $stat)
                        <div><dt class="visually-hidden">{{ $stat['label'] }}</dt><dd><span class="stat-value">{{ $stat['value'] }}</span>{{ $stat['label'] }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </section>
        @break

    @case('cta')
        <section class="section">
            <div class="container">
                <div class="cta">
                    @if ($block['heading'])<h2>{{ $block['heading'] }}</h2>@endif
                    {!! $block['html'] !!}
                    @if ($block['link_url'] && $block['link_label'])
                        <a class="btn btn-primary" href="{{ $block['link_url'] }}">{{ $block['link_label'] }}</a>
                    @endif
                </div>
            </div>
        </section>
        @break

    @case('testimonials')
        @if ($block['data']->isNotEmpty())
            <section class="section section-alt">
                <div class="container">
                    <h2>{{ $block['heading'] ?: 'What founders say' }}</h2>
                    <div class="grid">
                        @foreach ($block['data'] as $testimonial)
                            <figure class="card">
                                <blockquote class="testimonial"><p>{{ $testimonial->quote }}</p></blockquote>
                                <figcaption><strong>{{ $testimonial->author_name }}</strong>@if ($testimonial->author_role), {{ $testimonial->author_role }}@endif</figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
        @break

    @case('partners')
        @if ($block['data']->isNotEmpty())
            <section class="section">
                <div class="container">
                    <h2>{{ $block['heading'] ?: 'Partners' }}</h2>
                    <ul class="partners" role="list">
                        @foreach ($block['data'] as $partner)
                            <li>
                                @php($inner = $partner->logo ? '<img src="' . e($partner->logo->url()) . '" alt="' . e($partner->name) . '" loading="lazy">' : e($partner->name))
                                @if ($partner->website)
                                    <a href="{{ $partner->website }}" rel="noopener" target="_blank">{!! $inner !!}</a>
                                @else
                                    {!! $inner !!}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
        @break

    @case('faqs')
        @if ($block['data']->isNotEmpty())
            <section class="section">
                <div class="container narrow">
                    <h2>{{ $block['heading'] ?: 'Frequently asked questions' }}</h2>
                    @foreach ($block['data'] as $faq)
                        <details class="faq"><summary>{{ $faq->question }}</summary>{!! \App\Support\Markdown::render($faq->answer) !!}</details>
                    @endforeach
                    @if ($block['link_url'] && $block['link_label'])<p><a href="{{ $block['link_url'] }}">{{ $block['link_label'] }}</a></p>@endif
                </div>
            </section>
        @endif
        @break

    @case('latest_posts')
        @if ($block['data']->isNotEmpty())
            <section class="section section-alt">
                <div class="container">
                    <h2>{{ $block['heading'] ?: 'Latest news' }}</h2>
                    <div class="grid">
                        @foreach ($block['data'] as $post)
                            @include('site.news.card', ['post' => $post])
                        @endforeach
                    </div>
                    @if ($block['link_url'] && $block['link_label'])<p><a href="{{ $block['link_url'] }}">{{ $block['link_label'] }}</a></p>@endif
                </div>
            </section>
        @endif
        @break
@endswitch
