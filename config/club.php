<?php

return [
    'id' => env('CLUB_ID', 'CLUB'),
    'iban' => env('CLUB_IBAN', ''),
    'domain' => env('CLUB_DOMAIN', 'example.com'),
    'federation_salt' => env('FEDERATION_SALT', 'default_salt'),
    'google_maps_key' => env('GOOGLE_MAPS_KEY', ''),

    // Inbound mail alias address: {mailbox}+{alias}@{domain}
    // e.g. cep+bureau@clubcep.eu, cep+event.42@clubcep.eu
    'mail_address' => env('CLUB_MAIL_ADDRESS', 'club@'.env('CLUB_DOMAIN', 'example.com')),

    // "From" address used for proxied conversations (never a member's address).
    'noreply_address' => env('CLUB_NOREPLY_ADDRESS', 'no-reply@'.env('CLUB_DOMAIN', 'example.com')),

    // Mailbox that receives a copy of every proxied conversation (dual Reply-To),
    // so the club retains a mailbox copy of the thread in addition to email_log.
    'log_mailbox' => env('CLUB_LOG_MAILBOX', 'mail-log@'.env('CLUB_DOMAIN', 'example.com')),
];
