@extends('layouts.admin')
@section('title', 'Feature flags')

@section('content')
    <div class="page-head"><h1>Feature flags</h1></div>
    <p class="hint">Features that connect to outside systems (single sign-on, Hive IMS, the AI assistant) stay off until their set-up is done. Each one lists what is still missing.</p>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable table">
        <table>
            <thead><tr><th scope="col">Feature</th><th scope="col">Description</th><th scope="col">State</th><th scope="col">Change</th></tr></thead>
            <tbody>
            @foreach ($flags as $flag)
                <tr>
                    <td>{{ $flag['label'] }}<br><code>{{ $flag['key'] }}</code></td>
                    <td>
                        {{ $flag['description'] }}
                        @if ($flag['missing'])
                            <div class="setup-needed">
                                <strong>{{ $flag['enabled'] ? 'Switched on, but not working until:' : 'Still needed before it works:' }}</strong>
                                <ul>
                                    @foreach ($flag['missing'] as $step)
                                        <li>{{ $step }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $flag['enabled'] ? 'on' : 'off' }}">{{ $flag['enabled'] ? 'On' : 'Off' }}</span>
                        @if ($flag['missing'])<br><span class="badge badge-draft">Setup needed</span>@endif
                    </td>
                    <td>
                        @if ($flag['locked'])
                            <span class="meta" title="{{ $flag['blocked_reason'] }}">{{ in_array($flag['key'], \App\Http\Controllers\Admin\FeatureFlagController::NOT_BUILT, true) ? 'Not built yet' : 'Locked' }}</span>
                        @else
                            <form method="post" action="{{ route('admin.features.update', $flag['key']) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="enabled" value="{{ $flag['enabled'] ? 0 : 1 }}">
                                <button type="submit" class="btn btn-icon {{ $flag['enabled'] ? 'btn-icon-success' : '' }}" data-tip="{{ $flag['enabled'] ? 'Turn off' : 'Turn on' }}"><i class="fas {{ $flag['enabled'] ? 'fa-toggle-on' : 'fa-toggle-off' }}" aria-hidden="true"></i><span class="visually-hidden">{{ $flag['enabled'] ? 'Turn off' : 'Turn on' }} {{ $flag['label'] }}</span></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
