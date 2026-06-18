@extends('Template::layouts.app')

@section('app')
    @stack('fbComment')

    <div class="preloader">
        <div class="preloader-container">
            <span class="animated-preloader"></span>
        </div>
    </div>

    @include('Template::partials.header')

    <div class="main-wrapper">
        @if (!request()->routeIs('home'))
            @include('Template::partials.breadcrumb')
        @endif

        @yield('content')

        @include('Template::partials.footer')
    </div>

    @include('partials.cookie')
@endsection

@push('style-lib')
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/main.css') }}">
@endpush

@push('style')
    {{-- Public-only redesign layer; loads after main.css, before color.php.
         Static ?v= cache-bust so returning visitors fetch fresh after a deploy
         (bump this when public-redesign.css changes). filemtime() is avoided —
         the cPanel split layout makes public_path() resolve to a missing dir. --}}
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/public-redesign.css') }}?v=5">
@endpush
