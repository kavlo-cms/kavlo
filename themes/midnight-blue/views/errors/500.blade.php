@php
    $code = '500';
    $title = 'Something went wrong';
    $message = 'The server hit an unexpected error while trying to render this page.';
    $details = null;
    $tips = [
        'Refresh the page to see if the issue was temporary.',
        'Return home and try the action again from a clean start.',
        'If the problem persists, check server logs and recent theme or plugin changes.',
    ];
@endphp

@include('theme::errors.partials.page')
