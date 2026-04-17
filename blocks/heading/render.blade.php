@php
    $level = $data['level'] ?? 'h2';
    $align = $data['align'] ?? 'left';
    $text  = $data['text']  ?? '';
    $alignClass = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$align] ?? 'text-left';
    $sizeClass  = ['h1' => 'text-5xl font-extrabold', 'h2' => 'text-4xl font-bold', 'h3' => 'text-3xl font-semibold', 'h4' => 'text-2xl font-semibold'][$level] ?? 'text-4xl font-bold';
@endphp
<div class="px-6 py-4 max-w-3xl mx-auto w-full">
    <{{ $level }} class="{{ $sizeClass }} {{ $alignClass }} text-foreground">{{ $text }}</{{ $level }}>
</div>
