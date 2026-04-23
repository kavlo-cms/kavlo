@php(
    $brandName = rescue(
        fn () => \App\Models\Setting::get('site_name', config('app.name')),
        config('app.name') !== 'Laravel' ? config('app.name') : 'Kavlo',
        false,
    )
)
@php($homePath = app(\App\Services\SiteLocaleManager::class)->pathForLocale('/', app(\App\Services\SiteLocaleManager::class)->currentLocale()))

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $code }} - {{ $title }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen overflow-hidden bg-slate-950 text-slate-200 selection:bg-sky-500/30">
        <div class="relative min-h-screen">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.18),_transparent_40%),linear-gradient(180deg,_rgba(15,23,42,0.96),_rgba(2,6,23,1))]"></div>
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-sky-500/60 to-transparent"></div>

            <main class="relative mx-auto flex min-h-screen w-full max-w-6xl items-center px-6 py-16">
                <div class="grid w-full gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                    <div class="space-y-6">
                        <a href="{{ $homePath }}" class="inline-flex items-center gap-3 text-sm font-semibold tracking-[0.3em] text-sky-400 uppercase">
                            <span class="inline-flex size-3 rounded-full bg-sky-400 shadow-[0_0_20px_rgba(56,189,248,0.75)]"></span>
                            {{ $brandName }}
                        </a>

                        <div class="space-y-4">
                            <p class="text-7xl font-black tracking-tight text-white sm:text-8xl">{{ $code }}</p>
                            <h1 class="max-w-xl text-4xl font-semibold tracking-tight text-white sm:text-5xl">{{ $title }}</h1>
                            <p class="max-w-2xl text-lg leading-8 text-slate-400">{{ $message }}</p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <a href="{{ $homePath }}" class="inline-flex items-center rounded-full bg-sky-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-sky-400">
                                Back home
                            </a>
                            <button type="button" onclick="window.location.reload()" class="inline-flex items-center rounded-full border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-900">
                                Try again
                            </button>
                        </div>

                        @if (!empty($details))
                            <div class="max-w-2xl rounded-2xl border border-slate-800 bg-slate-900/70 px-5 py-4 text-sm leading-6 text-slate-300 shadow-lg shadow-slate-950/40 backdrop-blur">
                                {{ $details }}
                            </div>
                        @endif
                    </div>

                    <div class="relative">
                        <div class="absolute inset-0 rounded-[2rem] bg-gradient-to-br from-sky-500/20 via-slate-900/10 to-fuchsia-500/10 blur-3xl"></div>
                        <div class="relative overflow-hidden rounded-[2rem] border border-slate-800 bg-slate-900/70 p-8 shadow-2xl shadow-slate-950/50 backdrop-blur">
                            <div class="flex items-center gap-2 text-xs font-semibold tracking-[0.3em] text-slate-500 uppercase">
                                <span class="inline-flex size-2 rounded-full bg-emerald-400"></span>
                                Midnight Blue
                            </div>
                            <div class="mt-8 space-y-4">
                                <div class="rounded-2xl border border-slate-800 bg-slate-950/80 p-5">
                                    <p class="text-sm font-medium text-slate-300">Status</p>
                                    <p class="mt-2 text-2xl font-semibold text-white">{{ $code }} / {{ $title }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-800 bg-slate-950/80 p-5">
                                    <p class="text-sm font-medium text-slate-300">What you can do</p>
                                    <ul class="mt-3 space-y-2 text-sm leading-6 text-slate-400">
                                        @foreach ($tips as $tip)
                                            <li class="flex items-start gap-3">
                                                <span class="mt-2 inline-flex size-1.5 rounded-full bg-sky-400"></span>
                                                <span>{{ $tip }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
