@php
    $text    = $data['text']    ?? 'Button';
    $url     = $data['url']     ?? '#';
    $newTab  = !empty($data['new_tab']);
    $align   = $data['align']   ?? 'center';
    $variant = $data['variant'] ?? 'primary';
    $size    = $data['size']    ?? 'md';
    $alignClass   = ['left' => 'justify-start', 'center' => 'justify-center', 'right' => 'justify-end'][$align] ?? 'justify-center';
    $variantClass = ['primary' => 'bg-primary text-primary-foreground', 'secondary' => 'bg-secondary text-secondary-foreground', 'outline' => 'border border-input bg-background', 'ghost' => ''][$variant] ?? 'bg-primary text-primary-foreground';
    $sizeClass    = ['sm' => 'px-4 py-1.5 text-sm', 'md' => 'px-6 py-2.5 text-base', 'lg' => 'px-8 py-3.5 text-lg'][$size] ?? 'px-6 py-2.5 text-base';
@endphp
<div class="flex py-6 px-4 {{ $alignClass }}">
    <a href="{{ $url }}" {{ $newTab ? 'target="_blank" rel="noopener"' : '' }}
       class="inline-flex items-center rounded-lg font-medium transition-colors {{ $variantClass }} {{ $sizeClass }}">
        {{ $text }}
    </a>
</div>
