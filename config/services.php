<?php

return [

    'postmark' => ['token' => env('POSTMARK_TOKEN')],
    'ses' => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'), 'region' => env('AWS_DEFAULT_REGION', 'us-east-1')],
    'resend' => [
        'key' => env('RESEND_KEY'),
        'key_secondary' => env('RESEND_KEY_SECONDARY'),
    ],

    'mailjet' => [
        'key' => env('MAILJET_KEY'),
        'secret' => env('MAILJET_SECRET'),
    ],

    'onemin' => [
        'key' => env('ONEMIN_AI_KEY'),
    ],

    'deepl' => [
        'key' => env('DEEPL_API_KEY'),
    ],

    'auth_base_url' => env('AUTH_BASE_URL'),

    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
    ],

    // Inbound mail processing
    // Mode 'maildir': reads from local Maildir (no extra services needed)
    // Mode 'imap': connects to remote mailbox (when mail is on another server)
    'inbound_mail' => [
        'enabled' => env('INBOUND_MAIL_ENABLED', false),
        'mode' => env('INBOUND_MAIL_MODE', 'maildir'),
        'maildir' => env('INBOUND_MAILDIR', '/home/inbound/Maildir'),
        'imap_host' => env('INBOUND_IMAP_HOST'),
        'imap_port' => env('INBOUND_IMAP_PORT', 993),
        'imap_user' => env('INBOUND_IMAP_USER'),
        'imap_password' => env('INBOUND_IMAP_PASSWORD'),
        'imap_encryption' => env('INBOUND_IMAP_ENCRYPTION', 'ssl'),
    ],

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
        'tenant' => env('MICROSOFT_TENANT_ID', 'common'),
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

    'old_sync' => [
        'url' => env('OLD_SYNC_URL', 'https://clubcep.eu/wrapp/api_sync.php'),
        'key' => env('OLD_SYNC_KEY', 'cep-sync-2026-hetzner'),
    ],

    'umami' => [
        'url' => env('UMAMI_URL'),
        'id' => env('UMAMI_WEBSITE_ID'),
    ],
];
