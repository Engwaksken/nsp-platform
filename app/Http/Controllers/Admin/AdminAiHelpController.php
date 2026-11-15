<?php

namespace App\Http\Controllers\Admin;

use App\Ai\Assistant;
use App\Analytics\Kpis;
use App\Builder\CurriculumDefinition;
use App\Http\Controllers\Controller;
use App\Models\Builder\BuilderCurriculum;
use App\Models\Growth\VgiFramework;
use App\Registry\RegistryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Context-aware AI help for KPI reports and selected admin content pages. */
class AdminAiHelpController extends Controller
{
    public function ask(Request $request, Assistant $assistant): RedirectResponse
    {
        $data = $request->validate([
            'context' => ['required', Rule::in(['kpis', 'growth', 'builder'])],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'days' => ['nullable', 'integer', Rule::in(array_keys(InsightsController::PERIODS))],
            'question' => ['required', 'string', 'min:5', 'max:4000'],
            'acknowledge' => ['accepted'],
        ]);

        [$context, $task, $panel] = $this->context($request, $data);
        try {
            $result = $assistant->askAdmin($request->user(), $task, $data['question'], mb_substr($context, 0, 12000));
        } catch (RegistryException $exception) {
            return back()->withInput()->with('admin_ai_panel', $panel)->withErrors(['admin_ai_error' => $exception->getMessage()]);
        }

        if (! $result->ok()) {
            return back()->withInput()->with('admin_ai_panel', $panel)->withErrors(['admin_ai_error' => 'AI could not provide an answer this time. Check the service and try again.']);
        }

        return back()->with('admin_ai_result', [
            'panel' => $panel, 'text' => $result->text, 'model' => $result->model ?? 'AI model', 'generated_at' => now()->format('j M Y H:i'),
        ]);
    }

    private function context(Request $request, array $data): array
    {
        if ($data['context'] === 'kpis') {
            abort_unless($request->user()->can('view-insights'), 403);
            $days = (int) ($data['days'] ?? 30);
            $snapshot = Kpis::forDays($days);
            $rows = collect($snapshot->all())->map(fn ($kpi) => $kpi['group'].' · '.$kpi['label'].': '.$kpi['value'].' — '.$kpi['detail']);
            $monthly = collect(Kpis::monthly(6))->map(fn ($row) => $row['month'].': '.$row['users'].' new accounts, '.$row['startups'].' new startups');

            return ["Reporting period: last {$days} days.\nAggregate KPIs:\n- ".$rows->implode("\n- ")."\nMonthly account and startup totals:\n- ".$monthly->implode("\n- "), 'admin_kpi_help', 'kpis'];
        }

        if ($data['context'] === 'growth') {
            abort_unless($request->user()->canAny(['edit-vgi-frameworks', 'approve-vgi-frameworks']), 403);
            $framework = VgiFramework::query()->findOrFail($data['subject_id'] ?? 0);
            $lines = [];
            foreach ($framework->definition['dimensions'] ?? [] as $dimension) {
                $lines[] = 'Area: '.$dimension['title'].' (weight '.$dimension['weight'].')';
                foreach ($dimension['questions'] ?? [] as $question) {
                    $options = collect($question['options'] ?? [])->map(fn ($option) => $option['label'].(isset($option['score']) ? ' ['.$option['score'].']' : ' [unscored]'))->implode('; ');
                    $lines[] = 'Question: '.$question['prompt'].' Answers: '.$options;
                }
            }

            return ['Draft rubric “'.$framework->name.'” version '.$framework->version.":\n".implode("\n", $lines), 'admin_growth_help', 'growth-'.$framework->id];
        }

        abort_unless($request->user()->canAny(['manage-builder-curricula', 'publish-builder-curricula']), 403);
        $curriculum = BuilderCurriculum::query()->findOrFail($data['subject_id'] ?? 0);
        $definition = CurriculumDefinition::fromCurriculum($curriculum);
        $lines = [];
        foreach ($definition['modules'] ?? [] as $module) {
            $lines[] = 'Module: '.$module['title'].'. '.($module['description'] ?? '');
            foreach ($module['steps'] ?? [] as $step) {
                $lines[] = 'Step: '.$step['title'].'. '.($step['guidance'] ?? '');
                foreach ($step['fields'] ?? [] as $field) {
                    $lines[] = 'Founder prompt: '.$field['label'];
                }
            }
        }

        return ['Curriculum “'.$curriculum->name.'” version '.$curriculum->version.":\n".implode("\n", $lines), 'admin_builder_help', 'builder-'.$curriculum->id];
    }
}
