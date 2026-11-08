<?php

namespace App\Http\Controllers\Workspace;

use App\Ai\Assistant;
use App\Http\Controllers\Controller;
use App\Models\Funding\FundingRequest;
use App\Models\Registry\Startup;
use App\Registry\RegistryException;
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
