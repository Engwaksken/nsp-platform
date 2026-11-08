@extends('layouts.public', ['pageTitle' => 'Growth assessment · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>Growth assessment · {{ $startup->display_name }}</h1>
        @include('workspace.startups.nav')
        @include('partials.flash')
        @error('growth')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
        <p>Answer honestly. This is for your team’s planning, not a test. You can save and come back. {{ $assessment->framework->name }}, version {{ $assessment->framework->version }}.</p>

        <form method="post" action="{{ route('workspace.growth.save', [$startup, $assessment]) }}">
            @csrf @method('PUT')
            @foreach ($definition['dimensions'] as $dimension)
                <section class="panel" aria-labelledby="dim-{{ $dimension['key'] }}">
                    <h2 id="dim-{{ $dimension['key'] }}">{{ $loop->iteration }}. {{ $dimension['title'] }}</h2>
                    @if (!empty($dimension['description']))<p class="hint">{{ $dimension['description'] }}</p>@endif
                    @foreach ($dimension['questions'] as $question)
                        @php($response = $responses->get($question['key']))
                        <fieldset class="form-group">
                            <legend class="label">{{ $question['prompt'] }}</legend>
                            @if (!empty($question['help']))<p class="hint">{{ $question['help'] }}</p>@endif
                            @foreach ($question['options'] as $option)
                                <label class="checkbox"><input type="radio" name="answers[{{ $question['key'] }}]" value="{{ $option['key'] }}"
                                    @checked(old('answers.'.$question['key'], $response?->option_key) === $option['key'])> <span>{{ $option['label'] }}</span></label>
                            @endforeach
                            @if (($question['evidence'] ?? 'none') !== 'none')
                                <label for="evidence-{{ $question['key'] }}" @class(['required' => $question['evidence'] === 'required'])>Evidence {{ $question['evidence'] === 'required' ? '' : '(optional)' }}</label>
                                <textarea id="evidence-{{ $question['key'] }}" name="evidence[{{ $question['key'] }}]" rows="2" maxlength="2000" placeholder="Briefly describe evidence that supports your answer (optional)"
                                          placeholder="For example: a document name, a number, or where Hive can see this">{{ old('evidence.'.$question['key'], $response?->evidence_note) }}</textarea>
                            @endif
                        </fieldset>
                    @endforeach
                </section>
            @endforeach
            <div class="actions">
                <button type="submit" name="intent" value="save" class="btn btn-secondary">Save and finish later</button>
                <button type="submit" name="intent" value="submit" class="btn btn-primary">Submit and see results</button>
            </div>
        </form>
    </div>
@endsection
