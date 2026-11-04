<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Post::query()
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->query('q').'%'))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.posts.index', compact('posts'));
    }

    public function create(): View
    {
        return view('admin.posts.form', $this->formData(new Post(['status' => 'draft'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $post = new Post($data);
        $post->author_id = $request->user()->id;
        $post->save();
        $post->categories()->sync($request->input('categories', []));
        Audit::log('post.created', $post, ['status' => $post->status]);

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Post created.');
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.form', $this->formData($post));
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $this->validated($request, $post);
        $post->update($data);
        $post->categories()->sync($request->input('categories', []));
        Audit::log('post.updated', $post, ['status' => $post->status]);

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Post saved.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        abort_if($post->status === 'published' && ! Gate::allows('publish-content'), 403, 'Only people who can publish may delete a live post.');
        $post->delete();
        Audit::log('post.deleted', $post);

        return redirect()->route('admin.posts.index')->with('status', 'Post moved to trash.');
    }

    private function formData(Post $post): array
    {
        return [
            'post' => $post,
            'categories' => Category::query()->orderBy('name')->get(),
            'media' => Media::query()->where('mime_type', 'like', 'image/%')->latest('id')->limit(200)->get(),
        ];
    }

    private function validated(Request $request, ?Post $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('posts', 'slug')->ignore($post?->id)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:100000'],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'status' => ['required', Rule::in(Post::STATUSES)],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
        ], ['slug.regex' => 'Use lower-case letters, numbers and single hyphens only.']);

        // Changing live content, publishing or unpublishing all need publishing rights.
        if (($data['status'] === 'published' || $post?->status === 'published') && ! Gate::allows('publish-content')) {
            abort(403, 'Publishing, unpublishing or editing live posts needs an editor with publishing rights.');
        }
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = $post?->published_at ?? now();
        }
        unset($data['categories']);

        return $data;
    }
}
