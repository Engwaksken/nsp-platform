<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Funding\InvestorProfile;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Staff check who an investor is before they can see any startup. */
class InvestorController extends Controller
{
    public const STATUSES = ['pending' => 'Waiting for review', 'verified' => 'Verified', 'rejected' => 'Not verified', 'suspended' => 'Suspended'];

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $profiles = InvestorProfile::query()->with('user')
            ->when(array_key_exists($status, self::STATUSES), fn ($q) => $q->where('status', $status))
            ->latest('updated_at')->paginate(25)->withQueryString();

        return view('admin.investors.index', ['profiles' => $profiles, 'status' => $status, 'statuses' => self::STATUSES]);
    }

    public function decide(Request $request, InvestorProfile $investor): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected', 'suspended'])],
            'verification_note' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $request->input('status') !== 'verified')],
        ]);
        $investor->forceFill([
            'status' => $data['status'],
            'verification_note' => $data['verification_note'] ?? null,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ])->save();

        $user = $investor->user;
        if ($user) {
            $data['status'] === 'verified' ? $user->assignRole('investor') : $user->removeRole('investor');
        }
        Audit::log('funding.investor_'.$data['status'], $investor, ['note' => $data['verification_note'] ?? null]);

        return back()->with('status', ($user?->name ?? 'Investor').': '.self::STATUSES[$data['status']].'.');
    }
}
