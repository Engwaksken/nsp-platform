<?php

namespace App\Http\Controllers\Workspace;

use App\Ai\Assistant;
use App\Http\Controllers\Controller;
use App\Models\Builder\VentureStepSubmission;
use App\Models\Funding\FundingRequest;
use App\Models\Growth\VgiAssessment;
use App\Models\Registry\Startup;
use App\Registry\RegistryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The founder's AI writing helper. The suggestion is shown once and never stored. */
class AssistantController extends Controller
{
    use ResolvesMembership;

    public function __construct(private readonly Assistant $assistant) {}

    public function show(Request $request, Startup $startup): View
    {
        return $this->page($request, $startup);
    }

    public function run(Request $request, Startup $startup): View|RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $data = $request->validate([
            'task' => ['required', Rule::in(array_keys(Assistant::TASKS))],
            'text' => ['required', 'string', 'min:10', 'max:'.config('nsp.ai.max_input_chars')],
        ]);
        try {
            $result = $this->assistant->run($startup, $request->user(), $data['task'], $data['text']);
        } catch (RegistryException $exception) {
            return back()->withErrors(['assistant' => $exception->getMessage()])->withInput();
        }

        return $this->page($request, $startup, $result, $data['task'], $data['text']);
    }

    /**
     * Help in context from another page (growth results, Venture Builder steps). Answers JSON for the page's script;
     * without JavaScript it goes back to the page with the answer flashed once (it is not stored).
     */
    public function ask(Request $request, Startup $startup): JsonResponse|RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $data = $request->validate([
            'task' => ['required', Rule::in(array_keys(Assistant::CONTEXT_TASKS))],
            'subject' => ['required', 'string', 'max:64'],
            'gap' => ['nullable', 'string', 'max:120'],
            'text' => ['nullable', 'string', 'max:'.config('nsp.ai.max_input_chars')],
            'panel' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/'],
        ]);
        $subject = Assistant::CONTEXT_TASKS[$data['task']]['subject'] === 'assessment'
            ? VgiAssessment::query()->where('startup_uuid', $startup->uuid)->whereKey($data['subject'])->first()
            : (ctype_digit($data['subject']) ? VentureStepSubmission::query()->where('startup_uuid', $startup->uuid)->whereKey((int) $data['subject'])->first() : null);
        abort_if($subject === null, 404);

        try {
            $result = $this->assistant->ask($startup, $request->user(), $data['task'], $subject, (string) ($data['text'] ?? ''), $data['gap'] ?? null);
        } catch (RegistryException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['assistant' => $exception->getMessage()])->withInput()->withFragment($data['panel'] ?? '');
        }

        $payload = [
            'status' => $result->status,
            'text' => $result->ok() ? $result->text : '',
            'message' => $result->ok() ? null : ($result->status === 'refused'
                ? 'The assistant could not help with this request. Try rewording it.'
                : 'The assistant could not answer just now. Try again later.'),
            'model' => $result->model,
            'generated_at' => now()->format('j M Y H:i'),
            'panel' => $data['panel'] ?? null,
        ];
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with('ai_result', $payload)->withFragment($data['panel'] ?? '');
    }

    public function consent(Request $request, Startup $startup): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $grant = $request->boolean('grant');
        $this->assistant->setConsent($request->user(), $grant);

        return back()->with('status', $grant ? 'The AI assistant is on for your account.' : 'The AI assistant is off for your account.');
    }

    private function page(Request $request, Startup $startup, $result = null, ?string $task = null, ?string $text = null): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $defaults = [
            'description' => (string) $startup->description,
            'pitch' => (string) FundingRequest::query()->where('startup_uuid', $startup->uuid)->where('status', 'open')->value('pitch'),
            'actions' => '',
        ];

        return view('workspace.startups.assistant', [
            'startup' => $startup,
            'membership' => $membership,
            'unavailable' => Assistant::unavailableReason(),
            'consented' => $this->assistant->hasConsent($request->user()),
            'result' => $result,
            'task' => $task ?? $request->query('task', 'description'),
            'text' => $text,
            'defaults' => $defaults,
        ]);
    }
}
