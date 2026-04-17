@php
    $type  = $data['type']  ?? 'info';
    $title = $data['title'] ?? '';
    $text  = $data['text']  ?? '';
    $classes = [
        'info'    => 'bg-blue-50 border-blue-200 dark:bg-blue-950/30 dark:border-blue-800',
        'success' => 'bg-green-50 border-green-200 dark:bg-green-950/30 dark:border-green-800',
        'warning' => 'bg-amber-50 border-amber-200 dark:bg-amber-950/30 dark:border-amber-800',
        'error'   => 'bg-red-50 border-red-200 dark:bg-red-950/30 dark:border-red-800',
    ][$type] ?? 'bg-blue-50 border-blue-200';
@endphp
<div class="py-4 px-6 max-w-3xl mx-auto w-full">
    <div class="rounded-lg border p-4 {{ $classes }}">
        @if($title)<p class="text-sm font-semibold text-foreground">{{ $title }}</p>@endif
        @if($text)<p class="mt-1 text-sm text-foreground/80">{{ $text }}</p>@endif
    </div>
</div>
