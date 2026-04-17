@extends('theme::layouts.app')

@section('content')
    <div class="page-blocks">
        @foreach($page->blocks ?? [] as $block)
            @includeFirst(
                ["theme::blocks.{$block['type']}.render", "blocks::{$block['type']}.render"],
                ['data' => $block['data'] ?? []]
            )
        @endforeach
    </div>
@endsection
