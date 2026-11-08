<?php

namespace App\Http\Controllers\Workspace;

use App\Builder\VentureBuilderService;
use App\Http\Controllers\Controller;
use App\Models\Builder\BuilderCurriculum;
use App\Models\Builder\BuilderDocument;
use App\Models\Builder\VentureBuilderEnrollment;
use App\Models\Builder\VentureStepEvidence;
use App\Models\Builder\VentureStepSubmission;
use App\Models\Registry\Startup;
use App\Registry\RegistryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BuilderController extends Controller
{
    use ResolvesMembership;

    public function __construct(private readonly VentureBuilderService $builder) {}

    public function index(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $enrollment = VentureBuilderEnrollment::query()->where('startup_uuid', $startup->uuid)
            ->where('status', 'active')->with('curriculum.modules.steps', 'submissions.step.module')->latest('id')->first();

        return view('workspace.builder.index', [
            'startup' => $startup,
            'membership' => $membership,
            'enrollment' => $enrollment,
            'curriculum' => $enrollment?->curriculum ?? BuilderCurriculum::current()?->load('modules.steps'),
            'progress' => $enrollment ? $this->builder->progress($enrollment) : ['done' => 0, 'total' => 0, 'percent' => 0],
            'submissions' => $enrollment?->submissions->keyBy('step_key') ?? collect(),
        ]);
    }

    public function start(Request $request, Startup $startup): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($membership->canEdit(), 403, 'Only startup editors can begin the Venture Builder.');
        try {
            $this->builder->enroll($startup, $request->user());
        } catch (RegistryException $exception) {
            return back()->withErrors(['builder' => $exception->getMessage()]);
        }

        return redirect()->route('workspace.builder', $startup)->with('status', 'Venture Builder started. Your progress will be saved as you work.');
    }

    public function edit(Request $request, Startup $startup, VentureStepSubmission $submission): View
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($submission->startup_uuid === $startup->uuid, 404);
        abort_unless($this->builder->isUnlocked($submission), 404, 'This step is still locked.');
        $submission->load('step.module');
        $submission->load('evidence.uploader', 'reviews.reviewer', 'document.versions.author');

        return view('workspace.builder.step', compact('startup', 'membership', 'submission'));
    }

    public function save(Request $request, Startup $startup, VentureStepSubmission $submission): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($submission->startup_uuid === $startup->uuid, 404);
        abort_unless($membership->canEdit(), 403, 'Only startup editors can save Builder steps.');
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'responses' => ['nullable', 'array'],
            'responses.*' => ['nullable', 'string', 'max:10000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['nullable', 'boolean'],
        ]);

        try {
            $this->builder->saveDraft($submission, $request->user(), $data['responses'] ?? [], $data['checklist'] ?? [], (int) $data['version']);
        } catch (RegistryException $exception) {
            return back()->withErrors(['builder' => $exception->getMessage()]);
        }

        return redirect()->route('workspace.builder.step', [$startup, $submission])->with('status', 'Draft saved. You can return to this step later.');
    }

    public function submit(Request $request, Startup $startup, VentureStepSubmission $submission): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($submission->startup_uuid === $startup->uuid, 404);
        abort_unless($membership->canEdit(), 403, 'Only startup editors can submit Builder steps.');
        try {
            $this->builder->submit($submission, $request->user());
        } catch (RegistryException $exception) {
            return back()->withErrors(['builder' => $exception->getMessage()]);
        }

        return redirect()->route('workspace.builder', $startup)->with('status', 'Step submitted. The next step is now unlocked.');
    }

    public function review(Request $request, Startup $startup, VentureStepSubmission $submission): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        abort_unless($submission->startup_uuid === $startup->uuid, 404);
        abort_unless($request->user()->can('review-builder-steps'), 403);
        $data = $request->validate([
            'decision' => ['required', 'in:changes_requested,validated'],
            'feedback' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        try {
            $this->builder->review($submission, $request->user(), $data['decision'], $data['feedback']);
        } catch (RegistryException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()]);
        }

        return back()->with('status', 'Review recorded.');
    }

    public function addEvidence(Request $request, Startup $startup, VentureStepSubmission $submission): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($submission->startup_uuid === $startup->uuid, 404);
        abort_unless($membership->canEdit() && $submission->isEditable(), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'source_note' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url:http,https', 'max:2000', 'required_without:file'],
            'file' => ['nullable', 'file', 'max:'.config('builder.evidence.max_kb'), 'mimes:pdf,docx,xlsx,xls,pptx,csv,txt,png,jpg,jpeg', 'required_without:url'],
        ]);
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mime = $file->getMimeType();
            abort_unless(in_array($mime, config('builder.evidence.mime_types'), true), 422, 'Unsupported evidence file type.');
            $path = $file->store('venture-builder/'.$startup->uuid, config('builder.evidence.disk', 'local'));
            VentureStepEvidence::query()->create([
                'submission_id' => $submission->id, 'startup_uuid' => $startup->uuid, 'kind' => 'file',
                'title' => $data['title'], 'disk' => config('builder.evidence.disk', 'local'), 'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 200, ''), 'mime_type' => $mime,
                'size' => $file->getSize(), 'source_note' => $data['source_note'] ?? null, 'uploaded_by' => $request->user()->id,
            ]);
        } else {
            try {
                $this->builder->attachLink($submission, $request->user(), $data['title'], $data['url'], $data['source_note'] ?? null);
            } catch (RegistryException $exception) {
                return back()->withErrors(['evidence' => $exception->getMessage()]);
            }
        }

        return back()->with('status', 'Private evidence added to this startup step.');
    }

    public function downloadEvidence(Request $request, Startup $startup, VentureStepSubmission $submission, VentureStepEvidence $evidence)
    {
        $this->membershipOr404($request, $startup);
        abort_unless($submission->startup_uuid === $startup->uuid && $evidence->submission_id === $submission->id && $evidence->startup_uuid === $startup->uuid, 404);
        abort_unless($evidence->kind === 'file' && $evidence->path && Storage::disk($evidence->disk ?: 'local')->exists($evidence->path), 404);

        return Storage::disk($evidence->disk ?: 'local')->download($evidence->path, $evidence->original_name ?: 'evidence', [
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function deleteEvidence(Request $request, Startup $startup, VentureStepSubmission $submission, VentureStepEvidence $evidence): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($membership->canEdit(), 403);
        abort_unless($submission->startup_uuid === $startup->uuid && $evidence->submission_id === $submission->id && $evidence->startup_uuid === $startup->uuid, 404);
        abort_unless($submission->isEditable(), 403);
        $evidence->delete();

        return back()->with('status', 'Evidence removed.');
    }

    public function generateDocument(Request $request, Startup $startup, VentureStepSubmission $submission): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($submission->startup_uuid === $startup->uuid, 404);
        abort_unless($membership->canEdit(), 403);
        try {
            $this->builder->generateDocument($submission, $request->user());
        } catch (RegistryException $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }

        return back()->with('status', 'A new draft output version was generated from this step template.');
    }

    public function saveDocument(Request $request, Startup $startup, VentureStepSubmission $submission, BuilderDocument $document): RedirectResponse
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($submission->startup_uuid === $startup->uuid && $document->submission_id === $submission->id && $document->startup_uuid === $startup->uuid, 404);
        abort_unless($membership->canEdit(), 403);
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1'], 'body' => ['required', 'string', 'max:100000']]);
        try {
            $this->builder->saveDocument($document, $request->user(), $data['body'], (int) $data['expected_version']);
        } catch (RegistryException $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }

        return back()->with('status', 'Document saved as a new version.');
    }

    public function exportDocument(Request $request, Startup $startup, VentureStepSubmission $submission, BuilderDocument $document)
    {
        $this->membershipOr404($request, $startup);
        abort_unless($submission->startup_uuid === $startup->uuid && $document->submission_id === $submission->id && $document->startup_uuid === $startup->uuid, 404);
        $version = $document->version();
        abort_unless($version !== null, 404);

        return response($version->body, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($document->title).'-v'.$version->number.'.md"',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
