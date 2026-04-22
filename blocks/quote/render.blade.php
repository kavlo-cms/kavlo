@php
    $text   = $data['text']   ?? '';
    $author = $data['author'] ?? '';
    $role   = $data['role']   ?? '';
    $textColor = kavlo_resolve_text_color($data['text_color'] ?? null);
    $widthClass = kavlo_block_width_class($data['width'] ?? null);
@endphp
<div class="mx-auto w-full px-6 py-8 {{ $widthClass }}">
    <blockquote class="border-l-4 border-primary pl-6">
        <p class="text-xl font-medium italic" style="color: {{ $textColor }}">{{ $text }}</p>
        @if($author || $role)
        <footer class="mt-3 text-sm">
            @if($author)<span class="font-semibold" style="color: {{ $textColor }}">{{ $author }}</span>@endif
            @if($author && $role)<span style="color: {{ $textColor }}; opacity: 0.8"> — </span>@endif
            @if($role)<span style="color: {{ $textColor }}; opacity: 0.8">{{ $role }}</span>@endif
        </footer>
        @endif
    </blockquote>
</div>
