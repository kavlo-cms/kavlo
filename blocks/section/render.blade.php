<div
    class="w-full {{ $data['padding'] ?? 'py-8 px-6' }}"
    @if(!empty($data['background']))style="background: {{ $data['background'] }}"@endif
>
    @foreach(($data['children'] ?? []) as $child)
        @includeFirst(
            ['theme::blocks.' . $child['type'] . '.render', 'blocks::' . $child['type'] . '.render'],
            ['data' => $child['data'] ?? []]
        )
    @endforeach
</div>
