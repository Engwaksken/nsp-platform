{{-- Common questions in a call-to-action band: intro and FAQs button on the left, the questions (expandable) on the right.
     Params: $faqs, optional $heading, $html (rendered intro), $linkLabel, $linkUrl, $bg (section background). --}}
@php($ctaId = 'faq-cta-'.($index ?? 'home'))
@php($bg ??= null)
<section class="section {{ $bg['class'] ?? '' }}" aria-labelledby="{{ $ctaId }}" @if (! empty($bg['style'])) style="{{ $bg['style'] }}" @endif>
    @include('site.section-bg')
    <div class="container">
        <div class="cta faq-cta">
            <div class="faq-cta-intro">
                <span class="faq-cta-icon" aria-hidden="true"><i class="fas fa-circle-question"></i></span>
                <h2 id="{{ $ctaId }}">{{ ($heading ?? '') ?: 'Frequently asked questions' }}</h2>
                @if (filled($html ?? null))
                    <div class="faq-cta-text">{!! $html !!}</div>
                @else
                    <p class="faq-cta-text">Quick answers about accounts, startups, data sharing, funding and the other services on {{ $brand['site_name'] }}.</p>
                @endif
                <a class="btn btn-primary" href="{{ ($linkUrl ?? '') ?: route('faqs') }}">{{ ($linkLabel ?? '') ?: 'View all FAQs' }} <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="faq-cta-list">
                @foreach ($faqs as $faq)
                    <details class="faq"><summary>{{ $faq->question }}</summary>{!! \App\Support\Markdown::render($faq->answer) !!}</details>
                @endforeach
            </div>
        </div>
    </div>
</section>
