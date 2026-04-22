@php
    $text    = $data['text']    ?? 'Button';
    $url     = $data['url']     ?? '#';
    $newTab  = !empty($data['new_tab']);
    $align   = $data['align']   ?? 'center';
    $variant = $data['variant'] ?? 'primary';
    $size    = $data['size']    ?? 'md';
    $tone    = $data['tone']    ?? 'brand';
    $gradient = $data['gradient'] ?? null;
    $radius  = $data['radius']  ?? 'rounded';
    $width   = $data['width']   ?? 'auto';
    $alignClass   = ['left' => 'justify-start', 'center' => 'justify-center', 'right' => 'justify-end'][$align] ?? 'justify-center';
    $variantClass = kavlo_button_variant_class($variant, $tone);
    $variantStyle = kavlo_gradient_background_style($gradient) ?: kavlo_button_variant_style($variant, $tone);
    $sizeClass    = ['sm' => 'px-4 py-1.5 text-sm', 'md' => 'px-6 py-2.5 text-base', 'lg' => 'px-8 py-3.5 text-lg'][$size] ?? 'px-6 py-2.5 text-base';
    $radiusClass = kavlo_button_radius_class($radius);
    $widthClass = kavlo_button_width_class($width);
@endphp
<div class="flex py-6 px-4 {{ $alignClass }}">
    <a href="{{ $url }}" {{ $newTab ? 'target="_blank" rel="noopener"' : '' }}
       class="inline-flex items-center font-medium transition-colors {{ $variantClass }} {{ $sizeClass }} {{ $radiusClass }} {{ $widthClass }}"
       @if($variantStyle) style="{{ $variantStyle }}" @endif>
        {{ $text }}
    </a>
</div>
