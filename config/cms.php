<?php

return [
    'api_keys' => [
        'graphql_rate_limit_per_minute' => env('CMS_GRAPHQL_API_KEY_RATE_LIMIT', 120),
    ],
    'graphql' => [
        'authenticated_rate_limit_per_minute' => env('CMS_GRAPHQL_AUTH_RATE_LIMIT', 240),
        'guest_rate_limit_per_minute' => env('CMS_GRAPHQL_GUEST_RATE_LIMIT', 60),
    ],
    'cache' => [
        'public_pages' => [
            'enabled' => env('CMS_PUBLIC_PAGE_CACHE_ENABLED', true),
            'ttl_seconds' => env('CMS_PUBLIC_PAGE_CACHE_TTL', 300),
        ],
        'menu_html_ttl_seconds' => env('CMS_MENU_CACHE_TTL', 3600),
    ],
    'mail' => [
        'queue' => [
            'connection' => env('CMS_MAIL_QUEUE_CONNECTION'),
            'name' => env('CMS_MAIL_QUEUE', 'mail'),
        ],
    ],
    'security' => [
        'headers' => [
            'content_security_policy' => env('CMS_CONTENT_SECURITY_POLICY', "base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'"),
            'permissions_policy' => env('CMS_PERMISSIONS_POLICY', 'accelerometer=(), autoplay=(), camera=(), display-capture=(), geolocation=(), gyroscope=(), microphone=(), midi=(), payment=(), publickey-credentials-get=(), usb=()'),
            'referrer_policy' => env('CMS_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
            'x_frame_options' => env('CMS_X_FRAME_OPTIONS', 'SAMEORIGIN'),
            'hsts_max_age' => (int) env('CMS_HSTS_MAX_AGE', 31536000),
            'hsts_include_subdomains' => env('CMS_HSTS_INCLUDE_SUBDOMAINS', true),
            'hsts_preload' => env('CMS_HSTS_PRELOAD', false),
        ],
    ],
    'storage' => [
        'public_disk' => env('CMS_PUBLIC_DISK', env('MEDIA_DISK', 'public')),
    ],
    'updates' => [
        'enabled' => env('CMS_UPDATE_CHECK_ENABLED', true),
        'release_api_url' => env('CMS_RELEASE_API_URL', 'https://api.github.com/repos/kavlo-cms/kavlo/releases/latest'),
        'release_repository_url' => env('CMS_RELEASE_REPOSITORY_URL', 'https://github.com/kavlo-cms/kavlo/releases'),
        'cache_ttl_minutes' => (int) env('CMS_UPDATE_CHECK_TTL', 360),
        'timeout_seconds' => (int) env('CMS_UPDATE_CHECK_TIMEOUT', 5),
        'plugin_cache_ttl_minutes' => (int) env('CMS_PLUGIN_UPDATE_CHECK_TTL', 360),
    ],
];
