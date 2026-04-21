@extends('theme::layouts.app')

@section('content')
    <section class="mx-auto max-w-4xl px-6 py-16">
        <div class="mb-8">
            <h1 class="text-4xl font-semibold tracking-tight text-white">Search</h1>
            <p class="mt-2 text-slate-400">Find published pages across the site.</p>
        </div>

        <form action="{{ url('/search') }}" method="get" class="mb-10">
            <input
                type="search"
                name="q"
                value="{{ $query }}"
                placeholder="Search the site..."
                class="w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-slate-100 placeholder:text-slate-500 focus:border-sky-500 focus:outline-none"
            />
        </form>

        @if(strlen($query) < 2)
            <div class="rounded-xl border border-dashed border-slate-800 bg-slate-900/40 p-6 text-sm text-slate-400">
                Enter at least two characters to search.
            </div>
        @elseif(empty($results))
            <div class="rounded-xl border border-dashed border-slate-800 bg-slate-900/40 p-6 text-sm text-slate-400">
                No results found for "{{ $query }}".
            </div>
        @else
            <div class="space-y-4">
                @foreach($results as $result)
                    <article class="rounded-xl border border-slate-800 bg-slate-900/60 p-5">
                        <div class="flex items-center justify-between gap-3">
                            <a href="{{ $result['path'] }}" class="text-xl font-medium text-white hover:text-sky-400">
                                {{ $result['title'] }}
                            </a>
                            <span class="rounded-full border border-slate-700 px-2 py-1 text-xs uppercase tracking-wide text-slate-400">
                                {{ $result['type'] }}
                            </span>
                        </div>
                        <p class="mt-2 font-mono text-xs text-sky-400">{{ $result['path'] }}</p>
                        <p class="mt-3 text-sm leading-6 text-slate-300">{{ $result['excerpt'] }}</p>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
