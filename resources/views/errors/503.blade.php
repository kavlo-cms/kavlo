@php
    $status = 503;
    $code = '503';
    $title = 'Temporarily unavailable';
    $message = "We're performing maintenance right now and will be back shortly.";
    $details = filled($exception?->getMessage()) ? $exception->getMessage() : null;
@endphp

@include('errors.themed')
