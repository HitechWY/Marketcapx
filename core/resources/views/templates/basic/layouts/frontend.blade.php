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

    {{-- Material M1: re-skin the PUBLIC MARKETING pages only. Gated to marketing
         routes so auth pages (user login/register also use this layout) stay
         unchanged. Loads AFTER public-redesign.css so its --pb-* token aliases
         win, re-pointing the existing dark theme onto Material light/dark tokens. --}}
    @if (request()->routeIs('home', 'blogs', 'blog.details', 'contact', 'pages', 'policy.pages', 'cookie.policy'))
        <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/material/material-public.css') }}?v=1">
    @endif
@endpush
