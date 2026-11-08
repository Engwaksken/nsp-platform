@extends('layouts.admin')
@section('title', 'System health')

@section('content')
    <div class="page-head"><h1>System health</h1></div>
    <p><span class="badge badge-{{ $overall === 'ok' ? 'on' : ($overall === 'fail' ? 'off' : 'draft') }}">{{ ['ok' => 'All checks pass', 'warn' => 'Needs attention', 'fail' => 'Something is wrong'][$overall] }}</span></p>
    <p class="hint">The same checks run with <code>php artisan nsp:health</code>. What to do for each is in <code>docs/operations-runbook.md</code>.</p>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Health checks">
        <table>
            <thead><tr><th scope="col">Check</th><th scope="col">Status</th><th scope="col">Detail</th></tr></thead>
            <tbody>
            @foreach ($checks as $check)
                <tr>
                    <th scope="row">{{ $check['label'] }}</th>
                    <td><span class="badge badge-{{ $check['status'] === 'ok' ? 'on' : ($check['status'] === 'fail' ? 'off' : 'draft') }}">{{ strtoupper($check['status']) }}</span></td>
                    <td>{{ $check['detail'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
