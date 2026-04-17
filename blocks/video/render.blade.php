@php
    $url     = $data['url']     ?? '';
    $caption = $data['caption'] ?? '';
    $embedUrl = null;
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $embedUrl = "https://www.youtube.com/embed/{$m[1]}";
    } elseif (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
        $embedUrl = "https://player.vimeo.com/video/{$m[1]}";
    }
@endphp
@if($embedUrl)
<div class="py-4 px-4 max-w-4xl mx-auto w-full">
    <div class="aspect-video overflow-hidden rounded-lg">
        <iframe src="{{ $embedUrl }}" class="h-full w-full" frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen></iframe>
    </div>
    @if($caption)
    <p class="mt-2 text-center text-sm text-muted-foreground">{{ $caption }}</p>
    @endif
</div>
@endif
