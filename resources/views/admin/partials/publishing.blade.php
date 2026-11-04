{{-- Status and publish date for pages and posts. $item is a Page or Post. --}}
@php($canPublish = auth()->user()->can('publish-content'))
<section class="panel" aria-labelledby="publishing-heading">
    <h2 id="publishing-heading">Publishing</h2>
    @if ($item->exists)
        <p>Current status: <span class="badge badge-{{ $item->displayStatus() }}">{{ ucfirst($item->displayStatus()) }}</span></p>
    @endif
    <div class="form-group">
        <label for="field-status">Status</label>
        <select id="field-status" name="status" required>
            <option value="draft" @selected(old('status', $item->status) === 'draft')>Draft (not visible)</option>
            @if ($canPublish || $item->status === 'published')
                <option value="published" @selected(old('status', $item->status) === 'published')>Published</option>
            @endif
            <option value="archived" @selected(old('status', $item->status) === 'archived')>Archived</option>
        </select>
        @unless ($canPublish)
            <p class="hint">You can save drafts. Someone with publishing rights must publish them, and only they can change a live item.</p>
        @endunless
    </div>
    @include('partials.field', [
        'name' => 'published_at', 'label' => 'Publish date and time', 'type' => 'datetime-local',
        'value' => $item->published_at?->format('Y-m-d\TH:i'),
        'hint' => 'Leave empty to publish now. A future time schedules it (' . config('app.timezone') . ').',
    ])
</section>
