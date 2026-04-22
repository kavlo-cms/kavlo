@php
    $textColor = kavlo_resolve_text_color($data['text_color'] ?? null);
    $textGradientStyle = kavlo_gradient_text_style($data['text_gradient'] ?? null);
    $widthClass = kavlo_block_width_class($data['width'] ?? null);
@endphp
<div class="mx-auto w-full px-6 py-8 {{ $widthClass }}">
    <p class="text-base leading-relaxed" style="{{ $textGradientStyle ?: "color: {$textColor}" }}">{{ $data['content'] ?? '' }}</p>
</div>
