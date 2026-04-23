@php
    $code = '404';
    $title = 'Page not found';
    $message = "The page you're looking for doesn't exist, may have moved, or was never published.";
    $details = null;
    $tips = [
        'Check the URL for typos or missing path segments.',
        'Return to the homepage and navigate from the main menu.',
        'If this was a broken link, update the source or add a redirect.',
    ];
@endphp

@include('theme::errors.partials.page')
