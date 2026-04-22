@php
    $level = $data['level'] ?? 'h2';
    $align = $data['align'] ?? 'left';
    $text  = $data['text']  ?? '';
    $textColor = kavlo_resolve_text_color($data['text_color'] ?? null);
    $textGradientStyle = kavlo_gradient_text_style($data['text_gradient'] ?? null);
    $widthClass = kavlo_block_width_class($data['width'] ?? null);
    $alignClass = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$align] ?? 'text-left';
    $sizeClass  = ['h1' => 'text-5xl font-extrabold', 'h2' => 'text-4xl font-bold', 'h3' => 'text-3xl font-semibold', 'h4' => 'text-2xl font-semibold'][$level] ?? 'text-4xl font-bold';
@endphp
<div class="mx-auto w-full px-6 py-4 {{ $widthClass }}">
    <{{ $level }} class="{{ $sizeClass }} {{ $alignClass }}" style="{{ $textGradientStyle ?: "color: {$textColor}" }}">{{ $text }}</{{ $level }}>
</div>
