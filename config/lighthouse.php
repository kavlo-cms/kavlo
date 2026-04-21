<?php

use App\Http\Middleware\AuthenticateGraphqlApiKey;
use Nuwave\Lighthouse\Http\Middleware\AcceptJson;
use Nuwave\Lighthouse\Http\Middleware\AttemptAuthentication;

return [
    'route' => [
        'uri' => '/graphql',
        'name' => 'graphql',
        'middleware' => [
            AcceptJson::class,
            AttemptAuthentication::class,
            AuthenticateGraphqlApiKey::class,
            'throttle:graphql',
        ],
    ],
];
