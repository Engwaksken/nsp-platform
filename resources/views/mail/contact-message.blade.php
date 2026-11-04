New message from the {{ \App\Support\Settings::get('site_name') }} contact form.

Topic: {{ \App\Models\ContactMessage::TOPICS[$contactMessage->topic] ?? $contactMessage->topic }}
From: {{ $contactMessage->name }} <{{ $contactMessage->email }}>
Subject: {{ $contactMessage->subject }}

{{ $contactMessage->message }}

Reply to this email to answer the sender. Manage messages in the admin area: {{ route('admin.messages.show', $contactMessage) }}
