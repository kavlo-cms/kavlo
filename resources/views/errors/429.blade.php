@php
    $status = 429;
    $code = '429';
    $title = 'Too many requests';
    $message = 'You have made too many requests in a short period. Please wait a moment and try again.';
    $details = null;
@endphp

@include('errors.themed')
