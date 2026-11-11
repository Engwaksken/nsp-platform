@extends('layouts.app', ['pageTitle' => 'Notification choices'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container section narrow">
        <h1>What we tell you about</h1>
        @include('partials.flash')
        <p>Choose, for each kind of update, whether you get an email, a notification on NSP, both or neither. Security emails (password resets, sign-in codes) are always sent.</p>
        <form method="post" action="{{ route('notifications.preferences.save') }}">
            @csrf @method('PUT')
            <div class="table-wrap" tabindex="0" role="region" aria-label="Notification choices">
                <table>
                    <thead><tr><th scope="col">Update</th><th scope="col">Email</th><th scope="col">On NSP</th></tr></thead>
                    <tbody>
                    @foreach ($types as $type => $definition)
                        <tr>
                            <th scope="row">{{ $definition['label'] }}<br><span class="meta">{{ $definition['description'] }}</span></th>
                            <td><label class="checkbox"><input type="checkbox" name="email[{{ $type }}]" value="1" @checked($preferences[$type]['email'])><span class="visually-hidden">Email for {{ $definition['label'] }}</span></label></td>
                            <td><label class="checkbox"><input type="checkbox" name="in_app[{{ $type }}]" value="1" @checked($preferences[$type]['in_app'])><span class="visually-hidden">On NSP for {{ $definition['label'] }}</span></label></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary">Save my choices</button>
        </form>
    </div>
@endsection
