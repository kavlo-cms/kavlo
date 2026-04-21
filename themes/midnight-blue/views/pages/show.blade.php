@extends('theme::layouts.app')

@section('content')
    @php
        $wrapStandardPage = ($page->type ?? 'page') === 'page';
        $contentHtml = trim((string) ($page->content ?? ''));
        $hasContent = $contentHtml !== '';
        $hasBlocks = !empty($page->blocks);
        $renderContentFirst = ($page->editor_mode ?? 'builder') === 'content';
    @endphp

    @if($wrapStandardPage)
        <div class="page-container container mx-auto px-6 py-10">
    @endif

    @if($renderContentFirst && $hasContent)
        <div class="page-content">
            {!! app(\App\Services\PageContentRenderer::class)->render($page) !!}
        </div>
    @endif

    @if($hasBlocks)
        <div class="page-blocks">
            @foreach($page->blocks ?? [] as $block)
                {!! kavlo_render_block(is_array($block) ? $block : []) !!}
            @endforeach
        </div>
    @endif

    @if(!$renderContentFirst && $hasContent)
        <div class="page-content">
            {!! app(\App\Services\PageContentRenderer::class)->render($page) !!}
        </div>
    @endif

    @if($wrapStandardPage)
        </div>
    @endif
@endsection
