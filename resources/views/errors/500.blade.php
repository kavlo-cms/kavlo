@php
    $status = 500;
    $code = '500';
    $title = 'Something went wrong';
    $message = 'We hit an unexpected error while loading this page.';
    $details = null;
@endphp

@include('errors.themed')
