<?php

return [

    'postmark' => ['token' => env('POSTMARK_TOKEN')],
    'ses' => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'), 'region' => env('AWS_DEFAULT_REGION', 'us-east-1')],
    'resend' => ['key' => env('RESEND_KEY')],

    // OAuth Providers
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
    ],
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
        'page_token' => env('FACEBOOK_PAGE_TOKEN'),
    ],
    'instagram' => [
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
    ],
    'x' => [
        'client_id' => env('X_CLIENT_ID'),
        'client_secret' => env('X_CLIENT_SECRET'),
        'redirect' => env('X_REDIRECT_URI'),
    ],
    'amazon' => [
        'client_id' => env('AMAZON_CLIENT_ID'),
        'client_secret' => env('AMAZON_CLIENT_SECRET'),
        'redirect' => env('AMAZON_REDIRECT_URI'),
    ],
];
