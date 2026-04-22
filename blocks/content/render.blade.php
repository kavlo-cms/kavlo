@if(isset($page) && $page instanceof \App\Models\Page)
    <div class="page-content">
        {!! app(\App\Services\PageContentRenderer::class)->render($page) !!}
    </div>
@endif
