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
        @vite(['resources/css/app.css', 'resources/css/enhanced-markdown.css', 'resources/css/unified-markdown-editor.css', 'resources/js/app.js', 'resources/js/rich-content-editor.js', 'resources/js/enhanced-markdown.js', 'resources/js/unified-markdown-editor.js'])

        <!-- User Format Preferences for JavaScript -->
        @auth
            <script>
                window.userFormatOptions = @json(userFormatOptions());
                window.currentLocale = '{{ app()->getLocale() }}';
            </script>
        @endauth

        <!-- Page Styles -->
        @stack('styles')

        <!-- Prevent flash of unstyled content for Alpine.js components -->
        <style>
            [x-cloak] { display: none !important; }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </main>
            
            <!-- Kids Mode Indicator (shown when active) -->
            @include('components.kids-mode-indicator')
        </div>
        
        <!-- Page Scripts -->
        @stack('scripts')
    </body>
</html>
