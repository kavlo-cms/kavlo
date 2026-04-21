<div
    class="w-full {{ $data['padding'] ?? 'py-8 px-6' }}"
    @if(!empty($data['background']))style="background: {{ $data['background'] }}"@endif
>
    @foreach(($data['children'] ?? []) as $child)
        {!! kavlo_render_block(is_array($child) ? $child : []) !!}
    @endforeach
</div>
