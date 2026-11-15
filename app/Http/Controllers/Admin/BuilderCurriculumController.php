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

        $definition = old('definition');
        $definition = is_array($definition) ? $definition : $this->forForm(CurriculumDefinition::fromCurriculum($curriculum));

        return view('admin.builder.edit', ['curriculum' => $curriculum, 'definition' => is_array($definition) ? $definition : CurriculumDefinition::fromCurriculum($curriculum)]);
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
        $data = $request->validate([
            'definition' => ['required', 'array'],
            'definition.modules' => ['required', 'array', 'min:1'],
            'definition.modules.*' => ['required', 'array'],
            'definition.modules.*.key' => ['required', 'string', 'max:80'],
            'definition.modules.*.title' => ['required', 'string', 'max:255'],
            'definition.modules.*.description' => ['nullable', 'string', 'max:5000'],
            'definition.modules.*.outputs_text' => ['nullable', 'string', 'max:10000'],
            'definition.modules.*.active' => ['nullable', 'boolean'],
            'definition.modules.*.steps' => ['required', 'array'],
            'definition.modules.*.steps.*' => ['required', 'array'],
            'definition.modules.*.steps.*.key' => ['required', 'string', 'max:80'],
            'definition.modules.*.steps.*.title' => ['required', 'string', 'max:255'],
            'definition.modules.*.steps.*.guidance' => ['nullable', 'string', 'max:20000'],
            'definition.modules.*.steps.*.evidence_requirement' => ['nullable', 'string', 'max:30'],
            'definition.modules.*.steps.*.prerequisites_text' => ['nullable', 'string', 'max:2000'],
            'definition.modules.*.steps.*.task_suggestions_text' => ['nullable', 'string', 'max:5000'],
            'definition.modules.*.steps.*.vgi_dimension_keys' => ['nullable', 'string', 'max:2000'],
            'definition.modules.*.steps.*.output_template' => ['nullable', 'string', 'max:20000'],
            'definition.modules.*.steps.*.active' => ['nullable', 'boolean'],
            'definition.modules.*.steps.*.requires_review' => ['nullable', 'boolean'],
            'definition.modules.*.steps.*.fields' => ['required', 'array'],
            'definition.modules.*.steps.*.fields.*' => ['required', 'array'],
            'definition.modules.*.steps.*.fields.*.key' => ['required', 'string', 'max:60'],
            'definition.modules.*.steps.*.fields.*.label' => ['required', 'string', 'max:255'],
            'definition.modules.*.steps.*.fields.*.type' => ['required', 'string', 'max:30'],
            'definition.modules.*.steps.*.fields.*.help' => ['nullable', 'string', 'max:2000'],
            'definition.modules.*.steps.*.fields.*.options_text' => ['nullable', 'string', 'max:5000'],
            'definition.modules.*.steps.*.fields.*.required' => ['nullable', 'boolean'],
            'definition.modules.*.steps.*.checklist' => ['nullable', 'array'],
            'definition.modules.*.steps.*.checklist.*.key' => ['required', 'string', 'max:60'],
            'definition.modules.*.steps.*.checklist.*.label' => ['required', 'string', 'max:255'],
        ]);
        $definition = $this->normalize($data['definition']);
        if (! is_array($definition)) {
            return back()->withErrors(['definition' => 'Enter a valid curriculum.'])->withInput();
        }
        $problems = CurriculumDefinition::structureProblems($definition);
        if ($problems !== []) {
            return back()->withErrors(['definition' => $problems])->withInput();
        }
        try {
            $this->curricula->updateDraft($curriculum, $request->user(), $definition);
        } catch (RegistryException $exception) {
            return back()->withErrors(['definition' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Draft curriculum saved. It remains unpublished.');
    }

    private function forForm(array $definition): array
    {
        $modules = $definition['modules'] ?? [];
        foreach ($modules as &$module) {
            if (! is_array($module)) {
                continue;
            }
            $module['outputs_text'] = implode("\n", $module['outputs'] ?? []);
            $steps = $module['steps'] ?? [];
            foreach ($steps as &$step) {
                if (! is_array($step)) {
                    continue;
                }
                $step['prerequisites_text'] = implode(', ', $step['prerequisites'] ?? []);
                $step['task_suggestions_text'] = implode("\n", $step['task_suggestions'] ?? []);
                $fields = $step['fields'] ?? [];
                foreach ($fields as &$field) {
                    if (! is_array($field)) {
                        continue;
                    }
                    $field['options_text'] = collect($field['options'] ?? [])->map(fn ($label, $key) => $key.' | '.$label)->implode("\n");
                }
                unset($field);
                $step['fields'] = $fields;
            }
            unset($step);
            $module['steps'] = $steps;
        }
        unset($module);
        $definition['modules'] = $modules;

        return $definition;
    }

    private function normalize(array $definition): array
    {
        $definition['modules'] = array_values(array_map(function ($module) {
            if (! is_array($module)) {
                return $module;
            }
            $module['active'] = filter_var($module['active'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $module['outputs'] = $this->lines($module['outputs_text'] ?? '');
            unset($module['outputs_text']);
            $module['steps'] = array_values(array_map(function ($step) {
                if (! is_array($step)) {
                    return $step;
                }
                $step['active'] = filter_var($step['active'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $step['requires_review'] = filter_var($step['requires_review'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $prerequisites = $this->lines($step['prerequisites_text'] ?? '', '/[,\r\n]+/');
                $step['prerequisites'] = $prerequisites === [] ? null : $prerequisites;
                $step['task_suggestions'] = $this->lines($step['task_suggestions_text'] ?? '');
                $step['vgi_dimension_keys'] = $this->lines($step['vgi_dimension_keys'] ?? '', '/[,\r\n]+/');
                unset($step['prerequisites_text'], $step['task_suggestions_text']);
                $step['fields'] = array_values(array_map(function ($field) {
                    if (! is_array($field)) {
                        return $field;
                    }
                    $field['required'] = filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $field['options'] = $this->optionLines($field['options_text'] ?? '');
                    unset($field['options_text']);

                    return $field;
                }, $step['fields'] ?? []));
                $step['checklist'] = array_values($step['checklist'] ?? []);
                $step['vgi_dimension_keys'] = array_values($step['vgi_dimension_keys'] ?? []);

                return $step;
            }, $module['steps'] ?? []));

            return $module;
        }, $definition['modules'] ?? []));

        return $definition;
    }

    private function lines(mixed $text, string $separator = '/\r\n|\r|\n/'): array
    {
        if (! is_string($text)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split($separator, $text) ?: []), fn ($line) => $line !== ''));
    }

    private function optionLines(mixed $text): array
    {
        $options = [];
        foreach ($this->lines($text) as $line) {
            [$key, $label] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($key !== '' && $label !== '') {
                $options[$key] = $label;
            }
        }

        return $options;
    }

    public function publish(Request $request, BuilderCurriculum $curriculum): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:10', 'max:5000']]);
        try {
            $this->curricula->publish($curriculum, $request->user(), $data['note']);
        } catch (RegistryException $exception) {
            return back()->withErrors(['publish' => $exception->getMessage()]);
        }

        return redirect()->route('admin.builder.index')->with('status', 'Curriculum published. Owner methodology approval is pending.');
    }
}
