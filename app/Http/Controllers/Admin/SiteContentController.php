<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Partner;
use App\Models\Testimonial;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Simple lists edited the same way: FAQs, testimonials and partners. */
class SiteContentController extends Controller
{
    private function types(): array
    {
        return [
            'faqs' => [
                'model' => Faq::class, 'singular' => 'FAQ', 'plural' => 'FAQs', 'title' => 'question',
                'fields' => [
                    'question' => ['label' => 'Question', 'type' => 'text', 'rules' => ['required', 'string', 'max:255']],
                    'answer' => ['label' => 'Answer (Markdown)', 'type' => 'textarea', 'rules' => ['required', 'string', 'max:5000']],
                    'topic' => ['label' => 'Topic', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:80']],
                ],
            ],
            'testimonials' => [
                'model' => Testimonial::class, 'singular' => 'testimonial', 'plural' => 'Testimonials', 'title' => 'author_name',
                'fields' => [
                    'author_name' => ['label' => 'Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:120']],
                    'author_role' => ['label' => 'Role or startup', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:160']],
                    'quote' => ['label' => 'Quote', 'type' => 'textarea', 'rules' => ['required', 'string', 'max:1500']],
                    'photo_media_id' => ['label' => 'Photo', 'type' => 'media', 'rules' => ['nullable', 'integer', 'exists:media,id']],
                    'consent_confirmed' => ['label' => 'The person agreed in writing to publish this quote and photo', 'type' => 'checkbox', 'rules' => ['nullable', 'boolean']],
                ],
            ],
            'partners' => [
                'model' => Partner::class, 'singular' => 'partner', 'plural' => 'Partners', 'title' => 'name',
                'fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:160']],
                    'website' => ['label' => 'Website', 'type' => 'url', 'rules' => ['nullable', 'url:https,http', 'max:255']],
                    'logo_media_id' => ['label' => 'Logo', 'type' => 'media', 'rules' => ['nullable', 'integer', 'exists:media,id']],
                ],
            ],
        ];
    }

    private function type(string $type): array
    {
        $definition = $this->types()[$type] ?? null;
        abort_if($definition === null, 404);

        return $definition + ['key' => $type];
    }

    public function index(string $type): View
    {
        $definition = $this->type($type);
        $items = $definition['model']::query()->orderBy('sort_order')->orderBy('id')->paginate(30);

        return view('admin.content.index', compact('definition', 'items'));
    }

    public function create(string $type): View
    {
        $definition = $this->type($type);

        return view('admin.content.form', ['definition' => $definition, 'item' => new $definition['model'](['is_published' => true]), 'media' => $this->images()]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $definition = $this->type($type);
        $item = $definition['model']::query()->create($this->validated($request, $definition));
        Audit::log($type.'.created', $item);

        return redirect()->route('admin.content.index', $type)->with('status', ucfirst($definition['singular']).' added.');
    }

    public function edit(string $type, int $id): View
    {
        $definition = $this->type($type);

        return view('admin.content.form', ['definition' => $definition, 'item' => $definition['model']::query()->findOrFail($id), 'media' => $this->images()]);
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $definition = $this->type($type);
        /** @var Model $item */
        $item = $definition['model']::query()->findOrFail($id);
        $item->update($this->validated($request, $definition));
        Audit::log($type.'.updated', $item);

        return redirect()->route('admin.content.index', $type)->with('status', ucfirst($definition['singular']).' saved.');
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $definition = $this->type($type);
        $item = $definition['model']::query()->findOrFail($id);
        $item->delete();
        Audit::log($type.'.deleted', $item);

        return back()->with('status', ucfirst($definition['singular']).' deleted.');
    }

    private function validated(Request $request, array $definition): array
    {
        $rules = array_map(fn ($field) => $field['rules'], $definition['fields']);
        $rules['sort_order'] = ['nullable', 'integer', 'min:0', 'max:9999'];
        $rules['is_published'] = ['nullable', 'boolean'];
        $data = $request->validate($rules);
        foreach ($definition['fields'] as $name => $field) {
            if ($field['type'] === 'checkbox') {
                $data[$name] = $request->boolean($name);
            }
        }
        $data['is_published'] = $request->boolean('is_published');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    private function images()
    {
        return Media::query()->where('mime_type', 'like', 'image/%')->latest('id')->limit(200)->get();
    }
}
