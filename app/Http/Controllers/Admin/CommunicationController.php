<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAnnouncement;
use App\Models\Communication\Announcement;
use App\Models\Communication\EmailDelivery;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Announcements and the email delivery log. */
class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        return view('admin.communications.index', [
            'announcements' => Announcement::query()->with('creator', 'sender')->latest()->limit(20)->get(),
            'deliveries' => EmailDelivery::query()->with('user:id,name')
                ->when(in_array($status, ['queued', 'sent', 'failed'], true), fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(30)->withQueryString(),
            'counts' => EmailDelivery::query()->where('created_at', '>=', now()->subDays(7))->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'status' => $status,
            'audienceSizes' => collect(Announcement::AUDIENCES)->map(fn ($label, $key) => SendAnnouncement::audienceQuery($key)->count())->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:2000'],
            'url' => ['nullable', 'url:https,http', 'max:500'],
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
        ]);
        $announcement = Announcement::query()->create($data + ['created_by' => $request->user()->id]);
        Audit::log('communications.announcement_drafted', $announcement);

        return back()->with('status', 'Announcement saved as a draft. Check it, then send.');
    }

    public function send(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($announcement->status === 'draft', 404);
        $announcement->forceFill(['status' => 'sending', 'sent_by' => $request->user()->id])->save();
        Audit::log('communications.announcement_sent', $announcement, ['audience' => $announcement->audience]);
        SendAnnouncement::dispatch($announcement);

        return back()->with('status', 'Sending. People who opted in to Hive news also get it by email.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        abort_unless($announcement->status === 'draft', 404);
        $announcement->delete();

        return back()->with('status', 'Draft deleted.');
    }
}
