<?php

namespace App\Http\Controllers;

use App\Ai\Assistant;
use App\Ai\ChatAttachment;
use App\Registry\RegistryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The site-wide chat helper (partials/site-widgets). It uses the AI provider and model an admin made active in
 * Admin → AI assistant, with the same switch, legal review, consent and daily limits as the other AI help.
 * The conversation is kept in the person's browser tab only; nothing typed or generated is stored here.
 */
class ChatbotController extends Controller
{
    public function __construct(private readonly Assistant $assistant) {}

    public function message(Request $request): JsonResponse
    {
        // With a file the browser sends multipart form data, where the history arrives as a JSON string.
        if (is_string($request->input('history'))) {
            $request->merge(['history' => json_decode($request->input('history'), true) ?: []]);
        }
        $data = $request->validate([
            'message' => [$request->hasFile('attachment') ? 'nullable' : 'required', 'string', 'max:'.Assistant::CHAT_MESSAGE_CHARS],
            'page' => ['nullable', 'string', 'max:200'],
            'history' => ['nullable', 'array', 'max:40'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.text' => ['required', 'string', 'max:6000'],
            'attachment' => ['nullable', 'file', 'max:'.ChatAttachment::MAX_KB, 'extensions:'.implode(',', array_keys(ChatAttachment::TYPES))],
        ], [
            'attachment.extensions' => 'You can attach '.ChatAttachment::typeList().' files.',
            'attachment.max' => 'Attach a file under '.(ChatAttachment::MAX_KB / 1024).' MB.',
            'attachment.uploaded' => 'The file could not be uploaded. Attach a file under '.(ChatAttachment::MAX_KB / 1024).' MB.',
        ]);

        try {
            $attachment = $request->hasFile('attachment') ? ChatAttachment::fromUpload($request->file('attachment')) : null;
            $result = $this->assistant->chat($request->user(), (string) ($data['message'] ?? ''), $data['history'] ?? [], (string) ($data['page'] ?? ''), $attachment);
        } catch (RegistryException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'status' => $result->status,
            'text' => $result->ok() ? $result->text : '',
            'message' => $result->ok() ? null : ($result->status === 'refused'
                ? 'The assistant could not help with that. Try asking in a different way.'
                : 'The assistant could not answer just now. Try again in a moment.'),
            'model' => $result->model,
            'attachment' => $attachment ? ['name' => $attachment->name, 'truncated' => $attachment->truncated] : null,
        ]);
    }

    public function consent(Request $request): JsonResponse|RedirectResponse
    {
        $grant = $request->boolean('grant');
        $this->assistant->setConsent($request->user(), $grant);
        $message = $grant ? 'AI chat is on for your account.' : 'AI chat is off for your account.';

        return $request->expectsJson()
            ? response()->json(['consented' => $grant, 'message' => $message])
            : back()->with('status', $message);
    }
}
