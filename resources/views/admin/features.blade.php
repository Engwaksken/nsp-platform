@extends('layouts.admin')
@section('title', 'Feature flags')

@section('content')
    <div class="page-head"><h1>Feature flags</h1></div>
    <p class="hint">Modules for later phases are locked until they are built, tested and approved.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th scope="col">Feature</th><th scope="col">Description</th><th scope="col">State</th><th scope="col">Change</th></tr></thead>
            <tbody>
            @foreach ($flags as $flag)
                <tr>
                    <td>{{ $flag['label'] }}<br><code>{{ $flag['key'] }}</code></td>
                    <td>{{ $flag['description'] }}</td>
                    <td><span class="badge badge-{{ $flag['enabled'] ? 'on' : 'off' }}">{{ $flag['enabled'] ? 'On' : 'Off' }}</span></td>
                    <td>
                        @if ($flag['locked'])
                            <span class="meta">Not built yet</span>
                        @else
                            <form method="post" action="{{ route('admin.features.update', $flag['key']) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="enabled" value="{{ $flag['enabled'] ? 0 : 1 }}">
                                <button type="submit" class="btn btn-secondary btn-sm">{{ $flag['enabled'] ? 'Turn off' : 'Turn on' }}<span class="visually-hidden"> {{ $flag['label'] }}</span></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
