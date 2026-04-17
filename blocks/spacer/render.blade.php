@php
    $heightClass = ['xs' => 'h-4', 'sm' => 'h-8', 'md' => 'h-16', 'lg' => 'h-32', 'xl' => 'h-64'][$data['size'] ?? 'md'] ?? 'h-16';
@endphp
<div class="{{ $heightClass }} w-full"></div>
