<section
    class="relative py-24 overflow-hidden bg-slate-950"
    @if(!empty($data['background_image']))
        style="background-image: url('{{ $data['background_image'] }}'); background-size: cover; background-position: center;"
    @endif
>
    @if(!empty($data['background_image']))
        <div class="absolute inset-0 bg-slate-950/60"></div>
    @endif
    <div class="container mx-auto px-6 relative z-10 text-center">
        <h1 class="text-6xl md:text-8xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-white to-slate-500 mb-6">
            {{ $data['headline'] ?? '' }}
        </h1>
        <p class="text-xl text-slate-400 max-w-2xl mx-auto mb-10">
            {{ $data['subheadline'] ?? '' }}
        </p>
        @if (!empty($data['children']))
            @foreach ($data['children'] as $child)
                @includeFirst(
                    ['theme::blocks.' . $child['type'] . '.render', 'blocks::' . $child['type'] . '.render'],
                    ['data' => $child['data'] ?? []]
                )
            @endforeach
        @endif
    </div>
    {{-- Decorative Glow --}}
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-sky-500/10 via-transparent to-transparent blur-3xl"></div>
</section>
