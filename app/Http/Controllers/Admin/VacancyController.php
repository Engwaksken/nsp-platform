<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Talent\Vacancy;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Staff oversight of the jobs board. Applications and CVs are not visible here. */
class VacancyController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');
        $vacancies = Vacancy::query()->with('startup')->withCount('applications')
            ->when(array_key_exists($status, Vacancy::STATUSES), fn ($q) => $q->where('status', $status))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.vacancies.index', ['vacancies' => $vacancies, 'status' => $status]);
    }

    public function moderate(Request $request, Vacancy $vacancy): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['remove', 'restore'])],
            'moderation_note' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $request->input('action') === 'remove')],
        ]);
        if ($data['action'] === 'remove') {
            $vacancy->forceFill(['status' => 'removed', 'moderation_note' => $data['moderation_note'], 'closed_at' => now()])->save();
        } else {
            abort_unless($vacancy->status === 'removed', 404);
            $vacancy->forceFill(['status' => 'draft', 'moderation_note' => null, 'closed_at' => null])->save();
        }
        Audit::log('talent.vacancy_'.$data['action'].'d', $vacancy, ['note' => $data['moderation_note'] ?? null]);

        return back()->with('status', $data['action'] === 'remove' ? 'Vacancy taken down. The startup sees your note.' : 'Vacancy restored as a draft.');
    }
}
