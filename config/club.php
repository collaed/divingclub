<?php

return [
    'id' => env('CLUB_ID', 'CEP'),
    'iban' => env('CLUB_IBAN', ''),
    'domain' => env('CLUB_DOMAIN', 'clubcep.eu'),
    'federation_salt' => env('FEDERATION_SALT', 'default_salt'),
    'google_maps_key' => env('GOOGLE_MAPS_KEY', ''),
];
