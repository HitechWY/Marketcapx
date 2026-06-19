<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Material theme (M0): apply the persisted light/dark scheme before paint
         (no flash). Same cookie/localStorage keys as the Blade layout so theme
         choice is continuous across Blade <-> Inertia pages during migration. --}}
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

    <title inertia>{{ config('app.name', 'MarketCap X') }}</title>

    {{-- Ziggy: expose only the allow-listed 'public' route group (config/ziggy.php),
         not the full application route table. See core/docs/INERTIA-PUBLIC-POC.md. --}}
    @routes('public')
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>

<body>
    @inertia
</body>

</html>
