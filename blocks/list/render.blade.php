@php
    $items     = $data['items'] ?? [];
    $isNumbered = ($data['style'] ?? 'bullet') === 'numbered';
    $tag       = $isNumbered ? 'ol' : 'ul';
    $listClass = $isNumbered ? 'list-decimal pl-5' : 'list-disc pl-5';
@endphp
<div class="py-6 px-6 max-w-3xl mx-auto w-full">
    <{{ $tag }} class="space-y-1.5 {{ $listClass }}">
        @foreach($items as $item)
        <li class="text-base text-foreground">{{ $item }}</li>
        @endforeach
    </{{ $tag }}>
</div>
