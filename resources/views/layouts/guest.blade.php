<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['he', 'ar']) ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Page Styles -->
        @stack('styles')
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div>
                <a href="/">
                    <svg class="w-20 h-20 fill-current text-gray-500" viewBox="0 0 316 316" xmlns="http://www.w3.org/2000/svg">
                        <path d="M158 0C70.8 0 0 70.8 0 158s70.8 158 158 158 158-70.8 158-158S245.2 0 158 0zM158 237c-43.5 0-79-35.5-79-79s35.5-79 79-79 79 35.5 79 79-35.5 79-79 79z"/>
                    </svg>
                </a>
            </div>

            @yield('content')
        </div>
        
        <!-- Page Scripts -->
        @stack('scripts')
    </body>
</html>
