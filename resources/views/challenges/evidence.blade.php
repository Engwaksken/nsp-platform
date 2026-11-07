{{-- Evidence list + add form. Expects $challenge, $storeRoute, $destroyRoute (route names), $downloadRoute. --}}
<section class="panel" aria-labelledby="evidence-heading">
    <h2 id="evidence-heading">Supporting evidence</h2>
    <p class="hint">Adding a source does not mean it is validated. Hive reviewers check each source; only reviewed sources are labelled as such on the public page. Uploaded documents are kept private.</p>
    @error('evidence')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @if ($challenge->evidence->isNotEmpty())
        <ul>
            @foreach ($challenge->evidence as $item)
                <li>
                    @if ($item->url)<a href="{{ $item->url }}" rel="noopener nofollow" target="_blank">{{ $item->source_title }}</a>@else{{ $item->source_title }}@endif
                    <span class="meta">· {{ config('challenges.evidence_types')[$item->evidence_type] ?? $item->evidence_type }}</span>
                    @if ($item->hasFile()) · <a href="{{ route('challenges.evidence.download', [$challenge, $item]) }}">{{ $item->original_name }}</a>@endif
                    @if ($item->validated)<span class="badge badge-on">Reviewed {{ $item->validated_at?->format('j M Y') }}</span>@else<span class="badge badge-off">Not yet reviewed</span>@endif
                    @if ($challenge->isEditable())
                        <form method="post" action="{{ route($destroyRoute, [$challenge, $item]) }}" class="inline" data-confirm="Remove this source?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-secondary btn-sm">Remove</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if ($challenge->isEditable())
        <form method="post" action="{{ route($storeRoute, $challenge) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-row">
                @include('partials.field', ['name' => 'source_title', 'label' => 'Source title', 'required' => true])
                @include('partials.field', ['name' => 'evidence_type', 'label' => 'Type', 'type' => 'select', 'options' => config('challenges.evidence_types'), 'required' => true])
            </div>
            <div class="form-row">
                @include('partials.field', ['name' => 'url', 'label' => 'Link', 'type' => 'url'])
                @include('partials.field', ['name' => 'file', 'label' => 'Or upload a document', 'type' => 'file', 'attrs' => 'accept=".pdf,.docx,.xlsx,.csv,.png,.jpg,.jpeg"', 'hint' => 'PDF, Word, Excel, CSV or image, up to 10 MB. Kept private.'])
            </div>
            <button type="submit" class="btn btn-secondary">Add source</button>
        </form>
    @endif
</section>
