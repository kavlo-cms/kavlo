@php
    // Get the page variable shared from the Controller
    $page = view()->getShared()['page'] ?? null;
    $metadata = $page->metadata ?? [];

    $siteName    = \App\Models\Setting::get('site_name', config('app.name'));
    $titleFormat = \App\Models\Setting::get('meta_title_format', '%page_title% | %site_name%');
    $pageMetaTitle = $page?->meta_title;
    $pageTitle   = $pageMetaTitle ?: ($page?->title ?? $siteName);
    $fullTitle   = str_replace(['%page_title%', '%site_name%'], [$pageTitle, $siteName], $titleFormat);

    $defaultDesc = \App\Models\Setting::get('meta_description', '');
    $description = $page?->meta_description ?: $defaultDesc;

    $ogImage  = $page?->og_image ?? '';
    $favicon  = \App\Models\Setting::get('favicon', '/favicon.ico');
    $headScripts = kavlo_scripts('head');
@endphp

{{-- Standard SEO --}}
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">

{{-- Open Graph --}}
<meta property="og:title" content="{{ $pageMetaTitle ?: $pageTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}">
@if($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
@endif

{{-- Favicon --}}
<link rel="icon" href="{{ $favicon }}">

{{-- Head scripts (analytics, custom scripts, embeds, etc.) --}}
@if($headScripts)
{!! $headScripts !!}
@endif

{{-- Theme Specific Assets handled by Vite --}}
@stack('head_scripts')
