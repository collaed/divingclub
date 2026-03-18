<?php

return [
    'id' => env('CLUB_ID', 'CLUB'),
    'iban' => env('CLUB_IBAN', ''),
    'domain' => env('CLUB_DOMAIN', 'example.com'),
    'federation_salt' => env('FEDERATION_SALT', 'default_salt'),
    'google_maps_key' => env('GOOGLE_MAPS_KEY', ''),
];
