@php
    $code = '429';
    $title = 'Too many requests';
    $message = 'You have made too many requests in a short period of time.';
    $details = null;
    $tips = [
        'Wait a short moment before trying again.',
        'Avoid repeatedly refreshing forms or protected routes.',
        'If this keeps happening, review rate limits for the affected endpoint.',
    ];
@endphp

@include('theme::errors.partials.page')
