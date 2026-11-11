@extends('layouts.workspace', ['pageTitle' => 'Investment readiness · '.$startup->display_name])

@push('head')<meta name="robots" content="noindex">@endpush

@section('workspace')
    <div class="container section">
        <p><a href="{{ $backUrl }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Capital Exchange</a></p>
        <div class="page-head">
            <div>
                <h1>Investment readiness · {{ $startup->display_name }}</h1>
                <p>Everything an investor could see if you shared every scope. Each investor sees only the parts you choose on the Sharing tab.</p>
            </div>
            <a class="btn btn-secondary" href="{{ route('workspace.capital.readiness', [$startup, 'print' => 1]) }}"><i class="fas fa-print" aria-hidden="true"></i> Printable report</a>
        </div>
        @include('partials.flash')

        @include('capital.report')
    </div>
@endsection
