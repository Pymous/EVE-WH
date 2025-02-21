<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'EVE-WH') }}</title>
    <link rel="icon" href="{{ Vite::asset('resources/img/logo.png') }}" type="image/png">

    <script src="https://kit.fontawesome.com/5065bebf8d.js" crossorigin="anonymous"></script>

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased bg-gray-950 text-slate-300">
    @inertia
</body>

</html>
