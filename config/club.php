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
];
