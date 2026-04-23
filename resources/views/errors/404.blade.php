@php
    $status = 404;
    $code = '404';
    $title = 'Page not found';
    $message = "The page you're looking for doesn't exist or may have moved.";
    $details = null;
@endphp

@include('errors.themed')
