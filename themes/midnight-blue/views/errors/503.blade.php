@php
    $code = '503';
    $title = 'Under maintenance';
    $message = "We're performing scheduled maintenance and will be back shortly.";
    $details = filled($exception?->getMessage()) ? $exception->getMessage() : null;
    $tips = [
        'Wait a few minutes and refresh the page.',
        'Check status messaging if maintenance details were provided.',
        'Retry once the maintenance window has ended.',
    ];
@endphp

@include('theme::errors.partials.page')
