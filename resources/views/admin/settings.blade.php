@extends('layouts.admin')
@section('title', 'Branding and settings')

@section('content')
    <div class="page-head"><h1>Branding and settings</h1></div>
    @php($imageOptions = $images->mapWithKeys(fn ($m) => [$m->id => $m->original_name])->all())

    <form method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <section class="panel" aria-labelledby="identity-heading">
            <h2 id="identity-heading">Site identity</h2>
            @include('partials.field', ['name' => 'site_name', 'label' => 'Site name', 'value' => $settings['site_name'], 'required' => true, 'maxlength' => 80])
            @include('partials.field', ['name' => 'site_tagline', 'label' => 'Tagline', 'value' => $settings['site_tagline'], 'maxlength' => 160])
            @include('partials.field', ['name' => 'meta_description', 'label' => 'Default search description', 'type' => 'textarea', 'rows' => 2, 'value' => $settings['meta_description']])
            <div class="form-row">
                @include('partials.field', ['name' => 'logo_media_id', 'label' => 'Logo', 'type' => 'select', 'options' => $imageOptions, 'placeholder' => 'None', 'value' => $settings['logo_media_id']])
                @include('partials.field', ['name' => 'favicon_media_id', 'label' => 'Favicon', 'type' => 'select', 'options' => $imageOptions, 'placeholder' => 'None', 'value' => $settings['favicon_media_id'], 'hint' => 'A square PNG or ICO, 32×32 or larger.'])
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="logo-upload">Upload site logo</label>
                    <input id="logo-upload" type="file" name="logo_upload" accept="image/jpeg,image/png,image/webp,image/gif">
                    <p class="hint">JPG, PNG, WebP or GIF; up to 5 MB. Uploading replaces the selected logo.</p>
                    @error('logo_upload')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label for="favicon-upload">Upload favicon</label>
                    <input id="favicon-upload" type="file" name="favicon_upload" accept="image/png,image/x-icon">
                    <p class="hint">PNG or ICO; up to 5 MB. Uploading replaces the selected favicon.</p>
                    @error('favicon_upload')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="panel" aria-labelledby="theme-heading">
            <h2 id="theme-heading">Colours and font</h2>
            <p class="hint">Use the approved Hive brand palette. Text on these colours is chosen automatically: white when it has enough contrast (WCAG AA: at least 4.5:1), dark text otherwise. In dark mode, links and buttons are lightened so they stay readable.</p>
            <div class="form-row">
                @foreach (['primary_color' => 'Primary colour', 'secondary_color' => 'Secondary colour', 'accent_color' => 'Accent (focus and highlights)'] as $key => $label)
                    @php($ratio = \App\Support\Contrast::againstWhite(old($key, $settings[$key])))
                    <div class="form-group">
                        <label for="field-{{ $key }}">{{ $label }}</label>
                        <input id="field-{{ $key }}" type="color" name="{{ $key }}" value="{{ old($key, $settings[$key]) }}" required>
                        <p class="hint">{{ $settings[$key] }} · contrast with white {{ number_format($ratio, 1) }}:1
                            @if ($ratio < 4.5) · dark text will be used on it @endif</p>
                        @error($key)<p class="error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
            @include('partials.field', ['name' => 'font_family', 'label' => 'Font', 'type' => 'select', 'options' => array_combine(array_keys(\App\Support\Settings::FONTS), array_keys(\App\Support\Settings::FONTS)), 'value' => $settings['font_family'], 'required' => true,
                'hint' => '"System" loads no web font and is fastest on slow connections.'])
        </section>

        <section class="panel" aria-labelledby="contact-heading">
            <h2 id="contact-heading">Contact details</h2>
            <div class="form-row">
                @include('partials.field', ['name' => 'contact_email', 'label' => 'Public email', 'type' => 'email', 'value' => $settings['contact_email'], 'hint' => 'Also receives contact-form messages unless a topic is routed below.'])
                @include('partials.field', ['name' => 'contact_phone', 'label' => 'Phone', 'value' => $settings['contact_phone']])
            </div>
            @include('partials.field', ['name' => 'contact_address', 'label' => 'Address', 'value' => $settings['contact_address']])
            @include('partials.field', ['name' => 'footer_text', 'label' => 'Footer text', 'type' => 'textarea', 'rows' => 2, 'value' => $settings['footer_text']])
        </section>

        <section class="panel" aria-labelledby="routing-heading">
            <h2 id="routing-heading">Contact form routing</h2>
            <p class="hint">Send each topic to a team inbox. Leave empty to use the public email.</p>
            <div class="form-row">
                @foreach ($topics as $topic => $label)
                    @include('partials.field', ['name' => "routing[$topic]", 'id' => "routing-$topic", 'label' => $label, 'type' => 'email', 'value' => $routing[$topic] ?? ''])
                @endforeach
            </div>
        </section>

        <section class="panel" aria-labelledby="social-heading">
            <h2 id="social-heading">Social links</h2>
            <div class="form-row">
                @foreach (\App\Http\Controllers\Admin\SettingsController::SOCIAL as $network => $label)
                    @include('partials.field', ['name' => "social[$network]", 'id' => "social-$network", 'label' => $label, 'type' => 'url', 'value' => $social[$network] ?? ''])
                @endforeach
            </div>
        </section>

        <button type="submit" class="btn btn-primary">Save settings</button>
    </form>
@endsection
