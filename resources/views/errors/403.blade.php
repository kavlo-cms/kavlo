@php
    $status = 403;
    $code = '403';
    $title = 'Access denied';
    $message = 'You do not have permission to view this page.';
    $details = null;
@endphp

@include('errors.themed')
