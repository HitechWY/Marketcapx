<!-- meta tags and other links -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Material theme (M0): apply the persisted light/dark scheme before paint.
         Inert until the admin panel adopts .md-* classes (phase M5). --}}
    <script>
        (function () {
            try {
                var m = document.cookie.match("(?:^|; )md_theme=([^;]*)");
                var t = m ? decodeURIComponent(m[1]) : (localStorage.getItem("md-theme") || "dark");
                document.documentElement.setAttribute("data-md-theme", t === "light" ? "light" : "dark");
            } catch (e) {
                document.documentElement.setAttribute("data-md-theme", "dark");
            }
        })();
    </script>
    <title>{{ gs()->siteName($pageTitle ?? '') }}</title>

    <link rel="shortcut icon" type="image/png" href="{{ siteFavicon() }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/bootstrap.min.css') }}">

    <link rel="stylesheet" href="{{ asset('assets/admin/css/vendor/bootstrap-toggle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/global/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/global/css/line-awesome.min.css') }}">

    {{-- Material Design 3 system (M0 foundation): additive token + component
         layer for the admin panel. color.php is not loaded here, so
         --md-sys-color-primary uses the token fallback until M5 wires it. --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined&display=block">
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/material/material-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/material/material-components.css') }}">

    @stack('style-lib')

    <link rel="stylesheet" href="{{ asset('assets/global/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/app.css') }}">


    @stack('style')
</head>

<body>


    @yield('content')


    <script src="{{ asset('assets/global/js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('assets/global/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/vendor/bootstrap-toggle.min.js') }}"></script>


    @include('partials.notify')
    @stack('script-lib')

    <script src="{{ asset('assets/global/js/nicEdit.js') }}"></script>

    <script src="{{ asset('assets/global/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/app.js') }}"></script>
    {{-- Material Design 3 runtime (M0): theme toggle + ripple + drawer. --}}
    <script src="{{ asset(activeTemplate(true) . 'js/material.js') }}"></script>

    {{-- LOAD NIC EDIT --}}
    <script>
        "use strict";
        bkLib.onDomLoaded(function() {
            $(".nicEdit").each(function(index) {
                $(this).attr("id", "nicEditor" + index);
                new nicEditor({
                    fullPanel: true
                }).panelInstance('nicEditor' + index, {
                    hasPanel: true
                });
            });
        });
        (function($) {
            $(document).on('mouseover ', '.nicEdit-main,.nicEdit-panelContain', function() {
                $('.nicEdit-main').focus();
            });

            $('.breadcrumb-nav-open').on('click', function() {
                $(this).toggleClass('active');
                $('.breadcrumb-nav').toggleClass('active');
            });

            $('.breadcrumb-nav-close').on('click', function() {
                $('.breadcrumb-nav').removeClass('active');
            });

            if ($('.topTap').length) {
                $('.breadcrumb-nav-open').removeClass('d-none');
            }
        })(jQuery);
    </script>

    @stack('script')


</body>

</html>
