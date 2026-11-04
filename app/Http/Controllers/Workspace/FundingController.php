<?php

namespace App\Http\Controllers\Workspace;

use App\Funding\FundingService;
use App\Http\Controllers\Controller;
use App\Models\Funding\DataRoomAccessLog;
use App\Models\Funding\DataRoomFile;
use App\Models\Funding\DataRoomGrant;
use App\Models\Funding\FundingRequest;
use App\Models\Funding\Introduction;
use App\Models\Funding\InvestorProfile;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Models\User;
use App\Registry\RegistryException;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** A startup's funding page: request, matching investors, introductions and the private data room. */
class FundingController extends Controller
{
    use ResolvesMembership;

    /** Files a data room accepts, checked against the file's content, not its name. */
    public const MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'image/png', 'image/jpeg',
    ];

    public const EXTENSIONS = 'pdf,pptx,xlsx,xls,docx,png,jpg,jpeg';

    public const MAX_KB = 20480;

    public function __construct(private readonly FundingService $funding) {}

    public function index(Request $request, Startup $startup): View
    {
        $membership = $this->membershipOr404($request, $startup);
        $open = $this->funding->openRequest($startup);

        $matches = collect();
        if ($open) {
            $matches = InvestorProfile::query()->verified()->where('is_listed', true)->with('user')->get()
                ->map(fn (InvestorProfile $investor) => ['investor' => $investor] + $this->funding->fit($investor, $startup, $open))
                ->sortByDesc(fn ($match) => [$match['fits'], count($match['reasons'])])->values();
        }
        $introductions = Introduction::query()->with('investor', 'requester')->where('startup_uuid', $startup->uuid)->latest()->get();
        $profiles = InvestorProfile::query()->whereIn('user_id', $introductions->pluck('investor_user_id'))->get()->keyBy('user_id');

        return view('workspace.startups.funding', [
            'startup' => $startup,
            'membership' => $membership,
            'open' => $open,
            'past' => FundingRequest::query()->where('startup_uuid', $startup->uuid)->where('status', 'closed')->latest('closed_at')->limit(5)->get(),
            'consent' => $this->funding->listingConsent($startup),
            'matches' => $matches,
            'introductions' => $introductions,
            'profiles' => $profiles,
            'files' => DataRoomFile::query()->with('uploader')->where('startup_uuid', $startup->uuid)->latest()->get(),
            'grants' => DataRoomGrant::query()->active()->with('investor')->where('startup_uuid', $startup->uuid)->get(),
            'connected' => $introductions->where('status', 'accepted')->pluck('investor')->filter()->unique('id'),
            'accessLog' => DataRoomAccessLog::query()->with('user', 'file')->where('startup_uuid', $startup->uuid)->latest('accessed_at')->limit(30)->get(),
        ]);
    }

    public function saveRequest(Request $request, Startup $startup): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'currency' => ['required', Rule::in(InvestorProfile::CURRENCIES)],
            'instruments' => ['required', 'array', 'min:1'],
            'instruments.*' => [Rule::in(array_keys(InvestorProfile::INSTRUMENTS))],
            'pitch' => ['required', 'string', 'max:3000'],
            'use_of_funds' => ['nullable', 'string', 'max:3000'],
        ]);
        $open = $this->funding->openRequest($startup);
        if ($open) {
            $open->update($data);
        } else {
            $open = FundingRequest::query()->create($data + ['startup_uuid' => $startup->uuid, 'status' => 'open', 'created_by' => $request->user()->id]);
        }
        Audit::log('funding.request_saved', $startup, ['amount' => $open->amount, 'currency' => $open->currency]);

        return back()->with('status', 'Funding request saved.');
    }

    public function closeRequest(Request $request, Startup $startup): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        $open = $this->funding->openRequest($startup);
        abort_unless($open !== null, 404);
        $open->update(['status' => 'closed', 'closed_at' => now()]);
        Audit::log('funding.request_closed', $startup);

        return back()->with('status', 'Funding request closed. Investors can no longer find it. Existing connections and data-room access stay until you change them.');
    }

    public function upload(Request $request, Startup $startup): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(DataRoomFile::CATEGORIES))],
            'file' => ['required', 'file', 'max:'.self::MAX_KB, 'extensions:'.self::EXTENSIONS, 'mimetypes:'.implode(',', self::MIME_TYPES)],
        ]);
        $file = $request->file('file');
        $path = $file->store('data-room/'.$startup->uuid, 'local');
        $record = DataRoomFile::query()->create([
            'startup_uuid' => $startup->uuid, 'title' => $data['title'], 'category' => $data['category'], 'disk' => 'local', 'path' => $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200), 'mime_type' => (string) $file->getMimeType(),
            'size' => $file->getSize(), 'uploaded_by' => $request->user()->id,
        ]);
        Audit::log('funding.file_uploaded', $record, ['title' => $record->title]);

        return back()->with('status', 'Document added to your data room.');
    }

    public function destroyFile(Request $request, Startup $startup, DataRoomFile $file): RedirectResponse
    {
        $this->editorOr403($request, $startup);
        abort_unless($file->startup_uuid === $startup->uuid, 404);
        Audit::log('funding.file_deleted', $startup, ['title' => $file->title]);
        $file->delete();

        return back()->with('status', 'Document deleted.');
    }

    public function grant(Request $request, Startup $startup): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $data = $request->validate([
            'investor_user_id' => ['required', 'integer'],
            'expires_at' => ['nullable', 'date', 'after:today', 'before_or_equal:'.now()->addDays(180)->toDateString()],
        ]);
        $investor = User::query()->findOrFail($data['investor_user_id']);
        try {
            $this->funding->grant($startup, $request->user(), $investor, $data['expires_at'] ?? null);
        } catch (RegistryException $exception) {
            return back()->withErrors(['grant' => $exception->getMessage()]);
        }

        return back()->with('status', 'Data room shared with '.$investor->name.'.');
    }

    public function revoke(Request $request, Startup $startup, DataRoomGrant $grant): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        try {
            $this->funding->revoke($startup, $request->user(), $grant);
        } catch (RegistryException $exception) {
            return back()->withErrors(['grant' => $exception->getMessage()]);
        }

        return back()->with('status', 'Access removed. The investor can no longer open your documents.');
    }

    public function introduce(Request $request, Startup $startup): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        $data = $request->validate(['investor_user_id' => ['required', 'integer'], 'message' => ['required', 'string', 'max:2000']]);
        $investor = User::query()->findOrFail($data['investor_user_id']);
        try {
            $this->funding->requestIntroduction($startup, $request->user(), $investor, $data['message']);
        } catch (RegistryException $exception) {
            return back()->withErrors(['introduction' => $exception->getMessage()]);
        }

        return back()->with('status', 'Introduction sent. We will email you when the investor replies.');
    }

    public function decide(Request $request, Startup $startup, Introduction $introduction): RedirectResponse
    {
        $this->membershipOr404($request, $startup);
        abort_unless($introduction->startup_uuid === $startup->uuid, 404);
        try {
            $this->funding->decide($introduction, $request->user(), $request->input('decision') === 'accept');
        } catch (RegistryException $exception) {
            return back()->withErrors(['introduction' => $exception->getMessage()]);
        }

        return back()->with('status', $introduction->status === 'accepted' ? 'Connected. You can now see each other’s contact details.' : 'Introduction declined.');
    }

    private function editorOr403(Request $request, Startup $startup): StartupMembership
    {
        $membership = $this->membershipOr404($request, $startup);
        abort_unless($membership->canEdit(), 403, 'Only owners, founders and admins can change funding details.');

        return $membership;
    }
}
