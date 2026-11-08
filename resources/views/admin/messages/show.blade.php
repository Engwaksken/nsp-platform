@extends('layouts.admin')
@section('title', 'Message')

@section('breadcrumbs')
    <li><a href="{{ route('admin.messages.index') }}">Contact messages</a></li>
    <li aria-current="page">{{ $message->subject }}</li>
@endsection

@section('content')
    <div class="page-head"><h1>{{ $message->subject }}</h1></div>
    <section class="panel">
        <dl>
            <dt>From</dt><dd>{{ $message->name }} &lt;<a href="mailto:{{ $message->email }}">{{ $message->email }}</a>&gt;</dd>
            <dt>Topic</dt><dd>{{ \App\Models\ContactMessage::TOPICS[$message->topic] ?? $message->topic }}</dd>
            <dt>Received</dt><dd>{{ $message->created_at->format('j M Y H:i') }}</dd>
            <dt>Consent</dt><dd>{{ $message->consent_given ? 'Given' : 'Not given' }} (wording version {{ $message->consent_text_version }})</dd>
            <dt>Sent to</dt><dd>{{ $message->routed_to ?? 'Admin inbox only' }}</dd>
        </dl>
        <h2>Message</h2>
        <p>{!! nl2br(e($message->message)) !!}</p>
    </section>
    <form method="post" action="{{ route('admin.messages.update', $message) }}" class="panel filters">
        @csrf @method('PATCH')
        @include('partials.field', ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $message->status,
            'options' => collect(\App\Models\ContactMessage::STATUSES)->mapWithKeys(fn ($s) => [$s => ucfirst(str_replace('_', ' ', $s))])->all()])
        <button type="submit" class="btn btn-primary">Update</button>
    </form>
@endsection
