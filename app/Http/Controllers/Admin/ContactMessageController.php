<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ContactMessage::STATUSES, true) ? $request->query('status') : null;
        $messages = ContactMessage::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.messages.index', compact('messages', 'status'));
    }

    public function show(ContactMessage $message): View
    {
        // Reading personal data from the public form is logged.
        Audit::log('contact_message.viewed', $message);

        return view('admin.messages.show', compact('message'));
    }

    public function update(Request $request, ContactMessage $message): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(ContactMessage::STATUSES)]]);
        $message->update($data);
        Audit::log('contact_message.status_changed', $message, $data);

        return back()->with('status', 'Message updated.');
    }
}
