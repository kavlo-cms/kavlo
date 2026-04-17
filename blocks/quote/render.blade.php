@php
    $text   = $data['text']   ?? '';
    $author = $data['author'] ?? '';
    $role   = $data['role']   ?? '';
@endphp
<div class="py-8 px-6 max-w-3xl mx-auto w-full">
    <blockquote class="border-l-4 border-primary pl-6">
        <p class="text-xl font-medium italic text-foreground">{{ $text }}</p>
        @if($author || $role)
        <footer class="mt-3 text-sm">
            @if($author)<span class="font-semibold text-foreground">{{ $author }}</span>@endif
            @if($author && $role)<span class="text-muted-foreground"> — </span>@endif
            @if($role)<span class="text-muted-foreground">{{ $role }}</span>@endif
        </footer>
        @endif
    </blockquote>
</div>
