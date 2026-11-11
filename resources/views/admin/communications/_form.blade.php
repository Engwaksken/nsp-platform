{{-- Announcement fields, shared by "New announcement" and "Edit draft". Expects $announcement (or null) and $audienceSizes. --}}
@include('partials.field', ['name' => 'title', 'label' => 'Title', 'value' => $announcement?->title, 'required' => true, 'maxlength' => 200])
@include('partials.field', ['name' => 'body', 'label' => 'Message', 'type' => 'textarea', 'rows' => 5, 'value' => $announcement?->body, 'required' => true])
<div class="form-row">
    @include('partials.field', ['name' => 'url', 'label' => 'Link (optional)', 'type' => 'url', 'value' => $announcement?->url])
    @include('partials.field', ['name' => 'audience', 'label' => 'Audience', 'type' => 'select', 'required' => true, 'value' => $announcement?->audience,
        'options' => collect(\App\Models\Communication\Announcement::AUDIENCES)->map(fn ($label, $key) => $label.' ('.$audienceSizes[$key].')')->all()])
</div>
