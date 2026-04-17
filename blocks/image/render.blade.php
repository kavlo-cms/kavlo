@php
    $src     = $data['src']     ?? '';
    $alt     = $data['alt']     ?? '';
    $caption = $data['caption'] ?? '';
    $widthClass = ['full' => 'w-full', 'wide' => 'max-w-4xl mx-auto', 'medium' => 'max-w-2xl mx-auto', 'small' => 'max-w-sm mx-auto'][$data['width'] ?? 'full'] ?? 'w-full';
@endphp
@if($src)
<div class="py-4 px-4">
    <figure class="{{ $widthClass }}">
        <div class="overflow-hidden rounded-lg">
            <img src="{{ $src }}" alt="{{ $alt }}" class="h-auto w-full object-cover" />
        </div>
        @if($caption)
        <figcaption class="mt-2 text-center text-sm text-muted-foreground">{{ $caption }}</figcaption>
        @endif
    </figure>
</div>
@endif
