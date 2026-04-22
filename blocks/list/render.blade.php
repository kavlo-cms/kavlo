@php
    $items     = $data['items'] ?? [];
    $isNumbered = ($data['style'] ?? 'bullet') === 'numbered';
    $tag       = $isNumbered ? 'ol' : 'ul';
    $listClass = $isNumbered ? 'list-decimal pl-5' : 'list-disc pl-5';
    $textColor = kavlo_resolve_text_color($data['text_color'] ?? null);
    $widthClass = kavlo_block_width_class($data['width'] ?? null);
@endphp
<div class="mx-auto w-full px-6 py-6 {{ $widthClass }}" style="color: {{ $textColor }}">
    <{{ $tag }} class="space-y-1.5 {{ $listClass }}">
        @foreach($items as $item)
        <li class="text-base">{{ $item }}</li>
        @endforeach
    </{{ $tag }}>
</div>
