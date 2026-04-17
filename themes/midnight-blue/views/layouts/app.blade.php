<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {!! cms_head() !!}
    @vite(['resources/css/app.css'])
    @stack('styles')
</head>
<body class="bg-slate-950 text-slate-200 selection:bg-sky-500/30">
<div class="flex flex-col min-h-screen">
    {{-- Navigation --}}
    <header class="sticky top-0 z-50 bg-slate-950/80 backdrop-blur-md border-b border-slate-800">
        <div class="container mx-auto px-6 h-20 flex items-center justify-between">
            <a href="/" class="text-2xl font-bold tracking-tighter text-white">
                MIDNIGHT<span class="text-sky-500">.</span>
            </a>
            {!! cms_menu('main', ['container_class' => 'hidden md:flex space-x-8']) !!}
        </div>
    </header>

    {{-- Main Content - This is where blocks go --}}
    <main class="flex-grow">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-slate-900 border-t border-slate-800 py-12">
        <div class="container mx-auto px-6 text-center text-slate-500">
            <p>&copy; {{ date('Y') }} {{ \App\Models\Setting::get('site_name', config('app.name')) }}. Built with Midnight Blue.</p>
        </div>
    </footer>
</div>
@stack('scripts')
{!! \App\Models\Setting::get('body_scripts', '') !!}
</body>
</html>
