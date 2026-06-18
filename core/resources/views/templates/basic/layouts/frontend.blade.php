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
    {{-- Phase 2 public-only redesign layer; loads after main.css, before color.php. --}}
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/public-redesign.css') }}">
@endpush
