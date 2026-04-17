@php
    $style   = $data['style']   ?? 'line';
    $spacing = $data['spacing'] ?? 'md';
    $spacingClass = ['sm' => 'py-4', 'md' => 'py-8', 'lg' => 'py-16'][$spacing] ?? 'py-8';
@endphp
<div class="px-8 {{ $spacingClass }}">
    @if($style === 'line')
        <hr class="border-border" />
    @elseif($style === 'dots')
        <div class="flex items-center justify-center gap-2">
            <span class="h-1.5 w-1.5 rounded-full bg-muted-foreground/40"></span>
            <span class="h-1.5 w-1.5 rounded-full bg-muted-foreground/40"></span>
            <span class="h-1.5 w-1.5 rounded-full bg-muted-foreground/40"></span>
        </div>
    @endif
</div>
