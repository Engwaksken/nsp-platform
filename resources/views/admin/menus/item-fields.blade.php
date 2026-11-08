{{-- Fields for adding or editing one menu link. Expects $prefix (unique id prefix), $item (MenuItem or null), $pageOptions. --}}
<div class="form-group">
    <label for="{{ $prefix }}-label" class="required">Label</label>
    <input id="{{ $prefix }}-label" type="text" name="label" value="{{ $item?->label }}" required maxlength="80" placeholder="e.g. About us">
</div>
<div class="form-row">
    <div class="form-group">
        <label for="{{ $prefix }}-page">Page</label>
        <select id="{{ $prefix }}-page" name="page_id">
            <option value="">Custom link</option>
            @foreach ($pageOptions as $id => $title)<option value="{{ $id }}" @selected($item?->page_id === $id)>{{ $title }}</option>@endforeach
        </select>
    </div>
    <div class="form-group">
        <label for="{{ $prefix }}-url">Or link</label>
        <input id="{{ $prefix }}-url" type="text" name="url" value="{{ $item?->url }}" placeholder="/path or https://…">
        <p class="hint">Used when “Custom link” is chosen.</p>
    </div>
</div>
<div class="form-row">
    <div class="form-group">
        <label for="{{ $prefix }}-order">Order</label>
        <input id="{{ $prefix }}-order" type="number" name="sort_order" value="{{ $item?->sort_order ?? 0 }}" min="0" max="999">
    </div>
    <div class="form-group">
        <span class="label">Opens in</span>
        <label class="checkbox"><input type="checkbox" name="open_in_new_tab" value="1" @checked($item?->open_in_new_tab)> <span>A new browser tab</span></label>
    </div>
</div>
