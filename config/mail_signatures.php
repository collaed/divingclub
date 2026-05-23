<?php

/**
 * Signature stripping rules per sender domain.
 *
 * Each domain maps to anchors that mark the START of the signature block.
 * Everything from the anchor onwards is stripped.
 *
 * Keys: 'text_anchor' for plain text, 'html_anchor' for HTML bodies.
 * Use the exact string that begins the signature — no regex, no guessing.
 *
 * To find a sender's anchor: check EmailLog for their recent messages,
 * identify the static line that always starts their corporate footer.
 */
return [

    'tti-network.com' => [
        'text_anchor' => 'Keran CHAUSSARD',
        'html_anchor' => 'Keran CHAUSSARD',
    ],

    'ec.europa.eu' => [
        'text_anchor' => 'European Commission',
        'html_anchor' => 'European Commission',
    ],

    'europarl.europa.eu' => [
        'text_anchor' => 'European Parliament',
        'html_anchor' => 'European Parliament',
    ],

    'eib.org' => [
        'text_anchor' => 'European Investment Bank',
        'html_anchor' => 'European Investment Bank',
    ],

    // Global device/client footers (domain-independent)
    'global_device_footers' => [
        'Sent from my iPhone',
        'Sent from my iPad',
        'Sent from my Galaxy',
        'Sent from my Huawei',
        'Envoyé de mon iPhone',
        'Envoyé de mon iPad',
        'Get Outlook for iOS',
        'Get Outlook for Android',
        'Sent from Yahoo Mail',
        'Sent from Outlook for',
        'Télécharger Outlook pour',
    ],

];
