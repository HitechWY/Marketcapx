<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> {{ gs()->sitename(__($pageTitle)) }}</title>
    @include('partials.seo')

    <!-- bootstrap 4  -->
    <link rel="stylesheet" href="{{ asset('assets/global/css/bootstrap.min.css') }}">
    <!-- fontawesome 5  -->
    <link rel="stylesheet" href="{{ asset('assets/global/css/all.min.css') }}">
    <!-- lineawesome font -->
    <link rel="stylesheet" href="{{ asset('assets/global/css/line-awesome.min.css') }}">

    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'merchant/css/main.css') }}">

    {{-- Material M5: sensitive-UI visual layer (hosted checkout only — this is
         the checkout_master layout). material-tokens.css supplies the --md-sys-*
         shape/elevation tokens (this standalone layout doesn't otherwise load
         them); material-sensitive.css adds additive, theme-independent Material
         polish. Presentation only — no payment form contract touched. --}}
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/material/material-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/material/material-sensitive.css') }}?v=1">
</head>

<body>

    <!-- checkout section start -->
    @yield('content')
    <!-- checkout section end -->

    <!-- jQuery library -->
    <script src="{{ asset('assets/global/js/jquery-3.7.1.min.js') }}"></script>
    <!-- bootstrap js -->
    <script src="{{ asset('assets/global/js/bootstrap.bundle.min.js') }}"></script>
    @stack('script')
    @include('partials.notify')
</body>

</html>
