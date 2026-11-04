@extends('layouts.public', ['pageTitle' => 'Frequently asked questions', 'canonical' => route('faqs')])

@section('content')
    <div class="container section narrow">
        <h1>Frequently asked questions</h1>
        @forelse ($groups as $topic => $faqs)
            <h2>{{ $topic }}</h2>
            @foreach ($faqs as $faq)
                <details class="faq"><summary>{{ $faq->question }}</summary>{!! \App\Support\Markdown::render($faq->answer) !!}</details>
            @endforeach
        @empty
            <p class="empty">No questions have been published yet. <a href="{{ route('contact') }}">Contact us</a> with your question.</p>
        @endforelse
    </div>
@endsection
