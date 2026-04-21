@php
    $count = max(2, min(4, (int)($data['count'] ?? 2)));
    $gapClass = ['sm' => 'gap-2', 'md' => 'gap-6', 'lg' => 'gap-12'][$data['gap'] ?? 'md'] ?? 'gap-6';
@endphp
<div class="py-6 px-4">
    <div class="flex {{ $gapClass }}">
        @for($i = 0; $i < $count; $i++)
        <div class="min-w-0 flex-1">
            @foreach(($data['col_'.$i] ?? []) as $child)
                {!! kavlo_render_block(is_array($child) ? $child : []) !!}
            @endforeach
        </div>
        @endfor
    </div>
</div>
