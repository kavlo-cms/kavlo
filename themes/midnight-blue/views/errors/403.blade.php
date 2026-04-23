@php
    $code = '403';
    $title = 'Access denied';
    $message = 'This page exists, but you do not have permission to access it.';
    $details = null;
    $tips = [
        'Sign in with an account that has access to this area.',
        'Return to a public page or the homepage.',
        'If access should be granted, review role and permission settings.',
    ];
@endphp

@include('theme::errors.partials.page')
