<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ContactController extends Controller
{
    /** Forms submitted faster than this are almost always bots. */
    private const MIN_SECONDS = 3;

    public function show(Request $request): View
    {
        $request->session()->put('contact_form_started', now()->timestamp);

        return view('site.contact', ['topics' => ContactMessage::TOPICS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'topic' => ['required', Rule::in(array_keys(ContactMessage::TOPICS))],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'max:0'], // honeypot: real people never see this field
        ], [
            'consent.accepted' => 'Please agree to how we use your details so we can reply.',
            'website.max' => 'Your message could not be sent.',
        ]);

        $started = (int) $request->session()->pull('contact_form_started', 0);
        if ($started === 0 || now()->timestamp - $started < self::MIN_SECONDS) {
            return back()->withInput()->withErrors(['message' => 'Your message could not be sent. Please try again.']);
        }

        $routing = Settings::json('contact_routing');
        $routedTo = $routing[$data['topic']] ?? Settings::get('contact_email');

        $message = ContactMessage::query()->create([
            'topic' => $data['topic'],
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'consent_given' => true,
            'consent_text_version' => config('nsp.contact_consent_version'),
            'ip_hash' => Audit::hashIdentifier($request->ip()),
            'status' => 'new',
            'routed_to' => $routedTo,
        ]);

        if ($routedTo) {
            try {
                Mail::to($routedTo)->queue(new ContactMessageReceived($message));
            } catch (Throwable $exception) {
                // The message is saved; staff will still see it in the admin inbox.
                Log::warning('[contact] notification failed: '.$exception->getMessage());
            }
        }

        return redirect()->route('contact')->with('status', 'Thank you. Your message was sent and we will reply by email.');
    }
}
