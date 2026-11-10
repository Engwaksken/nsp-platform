<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Media;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public const SOCIAL = ['linkedin' => 'LinkedIn', 'x' => 'X (Twitter)', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube'];

    public function edit(): View
    {
        return view('admin.settings', [
            'settings' => Settings::all(),
            'social' => Settings::json('social_links'),
            'routing' => Settings::json('contact_routing'),
            'images' => Media::query()->where('mime_type', 'like', 'image/%')->latest('id')->limit(200)->get(),
            'topics' => ContactMessage::TOPICS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $color = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:80'],
            'site_tagline' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'primary_color' => $color,
            'secondary_color' => $color,
            'accent_color' => $color,
            'font_family' => ['required', Rule::in(array_keys(Settings::FONTS))],
            'logo_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'favicon_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'logo_upload' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,gif'],
            'favicon_upload' => ['nullable', 'file', 'max:5120', 'mimes:png,ico'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_address' => ['nullable', 'string', 'max:300'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'social' => ['nullable', 'array'],
            'social.*' => ['nullable', 'url:https', 'max:255'],
            'routing' => ['nullable', 'array'],
            'routing.*' => ['nullable', 'email', 'max:255'],
        ], [
            '*.regex' => 'Use a colour like #1d4ed8.',
            'social.*.url' => 'Social links must start with https://.',
        ]);

        foreach (['logo_upload' => 'logo_media_id', 'favicon_upload' => 'favicon_media_id'] as $uploadKey => $settingKey) {
            if (! $request->hasFile($uploadKey)) {
                continue;
            }

            $file = $request->file($uploadKey);
            $extension = strtolower($file->getClientOriginalExtension());
            $mime = (string) $file->getMimeType();
            $allowed = $uploadKey === 'favicon_upload'
                ? ['png' => ['image/png'], 'ico' => ['image/x-icon', 'image/vnd.microsoft.icon']]
                : ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'], 'gif' => ['image/gif']];

            if (! in_array($mime, $allowed[$extension] ?? [], true)) {
                return back()->withErrors([$uploadKey => 'The uploaded file contents do not match the selected image type.']);
            }

            $path = $file->storeAs('media/'.now()->format('Y/m'), Str::uuid().'.'.$extension, 'public');
            $media = Media::query()->create([
                'disk' => 'public', 'path' => $path,
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'mime_type' => $mime, 'size' => $file->getSize(),
                'alt_text' => $uploadKey === 'logo_upload' ? 'Site logo' : null,
                'uploaded_by' => $request->user()->id,
            ]);
            $data[$settingKey] = $media->id;
        }

        $social = array_filter(array_intersect_key($data['social'] ?? [], self::SOCIAL));
        $routing = array_filter(array_intersect_key($data['routing'] ?? [], ContactMessage::TOPICS));
        unset($data['social'], $data['routing']);
        $data['social_links'] = $social ? json_encode($social) : null;
        $data['contact_routing'] = $routing ? json_encode($routing) : null;

        Settings::put($data);
        Audit::log('settings.updated', 'site_settings', array_keys($data));

        return back()->with('status', 'Settings saved.');
    }
}
