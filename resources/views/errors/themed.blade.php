@php($themeView = "theme::errors.{$status}")
@php(
    $brandName = rescue(
        fn () => \App\Models\Setting::get('site_name', config('app.name')),
        config('app.name') !== 'Laravel' ? config('app.name') : 'Kavlo',
        false,
    )
)

@if (view()->exists($themeView))
    @include($themeView, ['exception' => $exception ?? null])
@else
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{{ $code }} - {{ $title }}</title>
            @vite(['resources/css/app.css'])
        </head>
        <body class="min-h-screen bg-slate-950 text-slate-200 selection:bg-sky-500/30">
            <main class="flex min-h-screen items-center justify-center px-6 py-16">
                <div class="w-full max-w-2xl rounded-3xl border border-slate-800 bg-slate-900/70 p-10 text-center shadow-2xl shadow-slate-950/50 backdrop-blur">
                    <p class="text-xs font-semibold tracking-[0.35em] text-sky-400 uppercase">{{ $brandName }}</p>
                    <p class="mt-6 text-7xl font-black tracking-tight text-white">{{ $code }}</p>
                    <h1 class="mt-4 text-3xl font-semibold text-white">{{ $title }}</h1>
                    <p class="mt-4 text-base leading-7 text-slate-400">{{ $message }}</p>

                    @if (!empty($details))
                        <div class="mt-6 rounded-2xl border border-slate-800 bg-slate-950/80 px-5 py-4 text-left text-sm leading-6 text-slate-300">
                            {{ $details }}
                        </div>
                    @endif

                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <a href="/" class="inline-flex items-center rounded-full bg-sky-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-sky-400">
                            Back home
                        </a>
                        <button type="button" onclick="window.location.reload()" class="inline-flex items-center rounded-full border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800">
                            Try again
                        </button>
                    </div>
                </div>
            </main>
        </body>
    </html>
@endif
