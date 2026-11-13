<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MediaController extends Controller
{
    /** Extension => allowed MIME types (checked from file contents, not the browser). */
    public const ALLOWED = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'],
        'gif' => ['image/gif'], 'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'], 'pdf' => ['application/pdf'],
        'mp4' => ['video/mp4'], 'webm' => ['video/webm'],
    ];

    /** Size limits in KB: short background videos may be larger than images and documents. */
    public const MAX_KB = 5120;

    public const MAX_VIDEO_KB = 20480;

    public function index(Request $request): View
    {
        $media = Media::query()
            ->when($request->query('type') === 'images', fn ($query) => $query->where('mime_type', 'like', 'image/%'))
            ->when($request->query('type') === 'videos', fn ($query) => $query->where('mime_type', 'like', 'video/%'))
            ->when($request->query('type') === 'documents', fn ($query) => $query->where('mime_type', 'not like', 'image/%')->where('mime_type', 'not like', 'video/%'))
            ->latest('id')
            ->paginate(24)
            ->withQueryString();

        return view('admin.media.index', compact('media'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.self::MAX_VIDEO_KB, 'mimes:'.implode(',', array_keys(self::ALLOWED))],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED[$extension] ?? [], true)) {
            return back()->withErrors(['file' => 'The file contents do not match its type. Upload a JPG, PNG, WebP, GIF, ICO, PDF, MP4 or WebM.']);
        }
        if (! str_starts_with($mime, 'video/') && $file->getSize() > self::MAX_KB * 1024) {
            return back()->withErrors(['file' => 'Images and documents can be up to 5 MB. Only videos may be up to 20 MB.']);
        }

        // Random names: never trust or reuse the uploaded file name on disk.
        $path = $file->storeAs('media/'.now()->format('Y/m'), Str::uuid().'.'.$extension, 'public');
        $media = Media::query()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'alt_text' => $request->input('alt_text'),
            'uploaded_by' => $request->user()->id,
        ]);
        Audit::log('media.uploaded', $media, ['name' => $media->original_name]);

        return back()->with('status', 'File uploaded.');
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $media->update($request->validate(['alt_text' => ['nullable', 'string', 'max:255']]));

        return back()->with('status', 'Description saved.');
    }

    public function destroy(Media $media): RedirectResponse
    {
        $media->delete();
        Audit::log('media.deleted', $media, ['name' => $media->original_name]);

        return back()->with('status', 'File deleted.');
    }
}
