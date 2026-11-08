<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Integration\ConsentedFields;
use App\Models\Integration\ApiClient;
use App\Models\Integration\ConnectCode;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupMembership;
use App\Registry\RegistryException;
use App\Registry\RegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The founder lets a connected system (IMS) use their startup profile
 * (contract §2). Redirects only ever go to a URI registered for the client.
 */
class ConnectController extends Controller
{
    public function __construct(private readonly RegistryService $registry) {}

    public function show(Request $request, string $clientId): View
    {
        [$client, $redirectUri, $state] = $this->validRequest($request, $clientId);
        $purpose = $client->consentPurpose();
        $memberships = StartupMembership::query()->with('startup')
            ->where('user_id', $request->user()->id)->where('status', 'active')
            ->whereHas('startup', fn ($q) => $q->where('status', 'active'))->get()
            ->map(function (StartupMembership $membership) use ($client) {
                $membership->setAttribute('sharing_on', ConsentedFields::consent($membership->startup, $client) !== null);

                return $membership;
            });

        return view('workspace.connect', [
            'client' => $client,
            'redirectUri' => $redirectUri,
            'state' => $state,
            'purpose' => config('nsp.registry.consent_purposes.'.$purpose),
            'memberships' => $memberships,
        ]);
    }

    public function decide(Request $request, string $clientId): RedirectResponse
    {
        [$client, $redirectUri, $state] = $this->validRequest($request, $clientId);
        if ($request->input('decision') !== 'approve') {
            return redirect()->away($this->withQuery($redirectUri, ['error' => 'access_denied', 'state' => $state]));
        }

        $startup = Startup::query()->where('status', 'active')->find((string) $request->input('startup_uuid'));
        $membership = $startup?->membershipOf($request->user());
        abort_unless($membership !== null, 404);
        abort_unless($membership->canEdit(), 403, 'Only owners, founders and admins can connect the startup to another Hive system.');

        if (! ConsentedFields::consent($startup, $client)) {
            try {
                // Turning sharing on here is the same consent as on the Data sharing page.
                $this->registry->grantConsent($startup, $request->user(), $client->consentPurpose());
            } catch (RegistryException $exception) {
                return back()->withErrors(['startup_uuid' => 'Sharing is off for '.$startup->display_name.'. Only an owner or founder can turn it on. Ask them, then try again.']);
            }
        }

        $code = Str::random(48);
        ConnectCode::query()->create([
            'code_hash' => hash('sha256', $code),
            'api_client_id' => $client->id,
            'startup_uuid' => $startup->uuid,
            'user_id' => $request->user()->id,
            'redirect_uri' => $redirectUri,
            'expires_at' => now()->addMinutes(5),
        ]);

        return redirect()->away($this->withQuery($redirectUri, ['code' => $code, 'state' => $state]));
    }

    /** @return array{0: ApiClient, 1: string, 2: string} */
    private function validRequest(Request $request, string $clientId): array
    {
        $client = ApiClient::query()->where('client_id', $clientId)->where('is_active', true)->first();
        $redirectUri = (string) $request->input('redirect_uri', '');
        $state = (string) $request->input('state', '');
        // Never redirect to an address that is not registered: show an error page instead.
        abort_unless($client && $client->consentPurpose() && $client->allowsRedirect($redirectUri), 400, 'This connection request is not valid.');
        abort_unless(strlen($state) >= 16 && strlen($state) <= 200 && preg_match('/^[A-Za-z0-9._~-]+$/', $state), 400, 'This connection request is not valid.');

        return [$client, $redirectUri, $state];
    }

    private function withQuery(string $uri, array $params): string
    {
        return $uri.(str_contains($uri, '?') ? '&' : '?').http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
