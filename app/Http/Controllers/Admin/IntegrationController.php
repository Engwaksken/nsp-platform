<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integration\OutboxDispatcher;
use App\Models\Integration\ApiClient;
use App\Models\Integration\InboxEvent;
use App\Models\Integration\WebhookDelivery;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Sync monitoring: connected systems, failed deliveries (with replay) and incoming events. */
class IntegrationController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.integrations', [
            'clients' => ApiClient::query()
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%')->orWhere('client_id', 'like', '%'.$request->query('q').'%')))
                ->when(in_array($request->query('client_status'), ['active', 'disabled'], true), fn ($q) => $q->where('is_active', $request->query('client_status') === 'active'))
                ->orderBy('name')->paginate(20, ['*'], 'clients_page')->withQueryString(),
            'counts' => WebhookDelivery::query()->selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status'),
            'problems' => WebhookDelivery::query()->with('event', 'client')
                ->where(fn ($q) => $q->where('status', 'dead')->orWhere(fn ($q) => $q->where('status', 'pending')->where('attempts', '>', 0)))
                ->latest('updated_at')->paginate(25, ['*'], 'problems_page')->withQueryString(),
            'inbox' => InboxEvent::query()->with('client')
                ->when($request->query('inbox') === 'rejected', fn ($q) => $q->where('status', 'rejected'))
                ->latest('received_at')->paginate(25, ['*'], 'inbox_page')->withQueryString(),
        ]);
    }

    public function replay(WebhookDelivery $delivery, OutboxDispatcher $dispatcher): RedirectResponse
    {
        $dispatcher->replay($delivery);
        Audit::log('integration.delivery_replayed', 'webhook_deliveries', ['delivery_id' => $delivery->id, 'event_id' => $delivery->event_id]);

        return back()->with('status', 'Queued again. It will be sent within a minute.');
    }
}
