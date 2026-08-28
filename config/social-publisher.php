<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | Which social publishing driver to use by default.
    | Add your own drivers at boot time via SocialPublisher::extend().
    |
    */

    'default' => env('SOCIAL_PUBLISHER_DRIVER', 'buffer'),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    |
    | Each driver has its own block. Keys vary per driver; all support
    | `default_profiles` — an array of profile IDs used by queueToAll().
    |
    */

    'drivers' => [

        'buffer' => [
            'access_token'       => env('BUFFER_ACCESS_TOKEN'),
            'base_url'           => env('BUFFER_BASE_URL', 'https://api.buffer.com/graphql'),
            'timeout'            => (int) env('BUFFER_TIMEOUT', 15),
            'connect_timeout'    => (int) env('BUFFER_CONNECT_TIMEOUT', 5),
            'profiles_cache_ttl' => (int) env('BUFFER_PROFILES_CACHE_TTL', 300),

            // Optional: profile IDs to use when calling queueToAll().
            // Leave empty to skip queueToAll() or supply IDs from your Buffer dashboard.
            'default_profiles'   => array_filter(explode(',', env('BUFFER_DEFAULT_PROFILES', ''))),
        ],

    ],

];
