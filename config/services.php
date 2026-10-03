<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'tcgdex' => [
        /*
         * The `?:` form is used so an env key that is present but empty
         * falls back to the default rather than resolving to an empty
         * string.
         */
        'base_url' => env('TCGDEX_API_URL') ?: 'https://api.tcgdex.net/v2/en',

        /*
         * The Pokémon name searched to populate the landing page featured
         * section. Kept in config so the curated pick can be changed
         * without touching the service.
         */
        'featured_card' => env('TCGDEX_FEATURED_CARD') ?: 'Charizard',
    ],
];
