<?php

namespace App\Http\Controllers\Admin;

use App\Builder\CurriculumDefinition;
use App\Builder\CurriculumService;
use App\Http\Controllers\Controller;
use App\Models\Builder\BuilderCurriculum;
use App\Registry\RegistryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuilderCurriculumController extends Controller
{
    public function __construct(private readonly CurriculumService $curricula) {}

    public function index(Request $request): View
    {
        $query = BuilderCurriculum::query()->with('editor', 'publisher')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
            ->when(array_key_exists($request->query('status'), BuilderCurriculum::STATUSES), fn ($q) => $q->where('status', $request->query('status')));

        return view('admin.builder.index', [
            'curricula' => $query->latest('version')->paginate(20)->withQueryString(),
            'curriculumStats' => [
                'total' => BuilderCurriculum::query()->count(),
                'drafts' => BuilderCurriculum::query()->where('status', 'draft')->count(),
                'published' => BuilderCurriculum::query()->where('status', 'published')->count(),
            ],
        ]);
    }

    public function edit(BuilderCurriculum $curriculum): View
    {
        abort_unless($curriculum->isDraft(), 404);

        return view('admin.builder.edit', ['curriculum' => $curriculum, 'definition' => CurriculumDefinition::fromCurriculum($curriculum)]);
    }

    public function createDraft(Request $request): RedirectResponse
    {
        try {
            $curriculum = $this->curricula->createDraft($request->user());
        } catch (RegistryException $exception) {
            return back()->withErrors(['curriculum' => $exception->getMessage()]);
        }

        return redirect()->route('admin.builder.edit', $curriculum)->with('status', 'A new unpublished draft version was created.');
    }

    public function update(Request $request, BuilderCurriculum $curriculum): RedirectResponse
    {
        abort_unless($curriculum->isDraft(), 404);
        $data = $request->validate(['definition' => ['required', 'string', 'max:2000000']]);
        $definition = json_decode($data['definition'], true);
        if (! is_array($definition)) {
            return back()->withErrors(['definition' => 'Enter a valid JSON curriculum object.'])->withInput();
        }
        try {
            $this->curricula->updateDraft($curriculum, $request->user(), $definition);
        } catch (RegistryException $exception) {
            return back()->withErrors(['definition' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Draft curriculum saved. It remains unpublished and provisional.');
    }

    public function publish(Request $request, BuilderCurriculum $curriculum): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:10', 'max:5000']]);
        try {
            $this->curricula->publish($curriculum, $request->user(), $data['note']);
        } catch (RegistryException $exception) {
            return back()->withErrors(['publish' => $exception->getMessage()]);
        }

        return redirect()->route('admin.builder.index')->with('status', 'Curriculum published as provisional. Owner methodology sign-off remains pending.');
    }
}
