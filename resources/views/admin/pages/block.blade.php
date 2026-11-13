{{-- One editable section. Field names are set by nsp.js from data-name; the server-rendered names work without JS. --}}
@php($i = $index ?? '__INDEX__')
<div class="block-editor">
    <div class="block-editor-head">
        <strong><span data-block-number>Section {{ is_int($i) ? $i + 1 : '' }}</span>: <span data-block-type-label>{{ \App\Models\Page::BLOCK_TYPES[$block['type']] ?? $block['type'] }}</span></strong>
        <div class="actions">
            <button type="button" class="btn btn-secondary btn-sm" data-block-action="up">Move up</button>
            <button type="button" class="btn btn-secondary btn-sm" data-block-action="down">Move down</button>
            <button type="button" class="btn btn-danger btn-sm" data-block-action="remove">Remove</button>
        </div>
    </div>
    <input type="hidden" data-name="type" name="blocks[{{ $i }}][type]" value="{{ $block['type'] }}">
    <div class="form-group">
        <label data-for="heading" for="block-{{ $i }}-heading">Heading</label>
        <input type="text" data-name="heading" id="block-{{ $i }}-heading" name="blocks[{{ $i }}][heading]" value="{{ $block['heading'] ?? '' }}" placeholder="Section heading" maxlength="200">
    </div>
    <div class="form-group">
        <label data-for="body" for="block-{{ $i }}-body">Text (Markdown)</label>
        <textarea data-name="body" id="block-{{ $i }}-body" name="blocks[{{ $i }}][body]" rows="4" placeholder="Write the text for this section…">{{ $block['body'] ?? '' }}</textarea>
    </div>
    <div class="form-group">
        <label data-for="items" for="block-{{ $i }}-items">Numbers (impact numbers only)</label>
        <textarea data-name="items" id="block-{{ $i }}-items" name="blocks[{{ $i }}][items]" rows="3" placeholder="120 | Startups supported">{{ $block['items'] ?? '' }}</textarea>
        <p class="hint">One per line: value | label. Only publish numbers you can back up.</p>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label data-for="link_label" for="block-{{ $i }}-link_label">Button or link text</label>
            <input type="text" data-name="link_label" id="block-{{ $i }}-link_label" name="blocks[{{ $i }}][link_label]" value="{{ $block['link_label'] ?? '' }}" placeholder="e.g. Learn more" maxlength="80">
        </div>
        <div class="form-group">
            <label data-for="link_url" for="block-{{ $i }}-link_url">Link address</label>
            <input type="text" data-name="link_url" id="block-{{ $i }}-link_url" name="blocks[{{ $i }}][link_url]" value="{{ $block['link_url'] ?? '' }}" placeholder="/contact or https://…">
        </div>
    </div>
    @php($bgMode = $block['background'] ?? '')
    <fieldset class="block-bg" data-bg-mode="{{ $bgMode }}">
        <legend>Background</legend>
        <div class="form-row">
            <div class="form-group">
                <label data-for="background" for="block-{{ $i }}-background">Type</label>
                <select data-name="background" id="block-{{ $i }}-background" name="blocks[{{ $i }}][background]" data-bg-switch>
                    @foreach (\App\Support\PageBlocks::BACKGROUNDS as $value => $label)
                        <option value="{{ $value }}" @selected($bgMode === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" data-bg-field="color">
                <label data-for="bg_color" for="block-{{ $i }}-bg_color">Colour</label>
                <input type="color" data-name="bg_color" id="block-{{ $i }}-bg_color" name="blocks[{{ $i }}][bg_color]" value="{{ ($block['bg_color'] ?? '') ?: '#0f3d5e' }}">
            </div>
            <div class="form-group" data-bg-field="media">
                <label data-for="bg_media_id" for="block-{{ $i }}-bg_media_id">Image or video</label>
                <select data-name="bg_media_id" id="block-{{ $i }}-bg_media_id" name="blocks[{{ $i }}][bg_media_id]">
                    <option value="">Choose a file</option>
                    @foreach (($backgrounds ?? collect())->groupBy(fn ($file) => $file->isVideo() ? 'Videos' : 'Images') as $group => $files)
                        <optgroup label="{{ $group }}">
                            @foreach ($files as $file)
                                <option value="{{ $file->id }}" @selected((string) ($block['bg_media_id'] ?? '') === (string) $file->id)>{{ $file->original_name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        </div>
        <p class="hint">Text turns light or dark to stay readable on a colour. Images and videos get a dark overlay with white text; videos play muted and on a loop, and stop for visitors who turn off motion. Upload images and MP4 or WebM videos (up to 20 MB) in the <a href="{{ route('admin.media.index') }}">media library</a>.</p>
    </fieldset>
</div>
