@php
    $widthMode = $data['width_mode'] ?? 'full-page-constrained';
    $sectionClasses = 'relative overflow-hidden bg-slate-950 py-24';
    $contentClasses = 'relative z-10 text-center';

    if (in_array($widthMode, ['full-page-constrained', 'full-page-unconstrained'], true)) {
        $sectionClasses .= ' left-1/2 right-1/2 w-screen max-w-none -translate-x-1/2';
    } else {
        $sectionClasses .= ' mx-auto max-w-screen-xl';
    }

    if ($widthMode === 'full-page-constrained') {
        $contentClasses .= ' mx-auto max-w-screen-xl px-6';
    } elseif ($widthMode === 'full-page-unconstrained') {
        $contentClasses .= ' w-full px-6';
    }
@endphp

<section
    class="{{ $sectionClasses }}"
    @if(!empty($data['background_image']))
        style="background-image: url('{{ $data['background_image'] }}'); background-size: cover; background-position: center;"
    @endif
>
    @if(!empty($data['background_image']))
        <div class="absolute inset-0 bg-slate-950/60"></div>
    @endif
    <div class="{{ $contentClasses }}">
        <h1 class="text-6xl md:text-8xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-white to-slate-500 mb-6">
            {{ $data['headline'] ?? '' }}
        </h1>
        <p class="text-xl text-slate-400 max-w-2xl mx-auto mb-10">
            {{ $data['subheadline'] ?? '' }}
        </p>
        @if (!empty($data['children']))
            @foreach ($data['children'] as $child)
                {!! kavlo_render_block(is_array($child) ? $child : []) !!}
            @endforeach
        @endif
    </div>
    {{-- Decorative Glow --}}
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-sky-500/10 via-transparent to-transparent blur-3xl"></div>
</section>
