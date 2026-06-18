@extends('Template::layouts.app')

@section('app')
    <div class="agent-dashboard">
        @include('Template::partials.agent_sidenav')
        @include('Template::partials.agent_topbar')

        <div class="agent-dashboard__body">
            @yield('content')
        </div>

    </div>

    @include('partials.sleep_mode', ['userType' => 'AGENT'])
@endsection

@push('style-lib')
    <!-- main css -->
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'agent/css/main.css') }}">
@endpush

@push('style')
    {{-- Material M3: dashboard shell re-skin (dashboard areas only — loaded from
         the dashboard masters, never public/auth/admin/checkout). Loads after
         agent main.css so the Material layer wins. Presentation only. --}}
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/material/material-dashboard.css') }}?v=1">
@endpush

@push('script-lib')
    <!-- main css -->
    <script src="{{ asset(activeTemplate(true) . 'agent/js/app.js') }}"></script>
    <script src="{{ asset('assets/global/js/jquery.slimscroll.min.js') }}"></script>
@endpush
