#!/usr/bin/env php
<?php

/**
 * License Key Generator for DivingClub
 *
 * Usage:
 *   1. Generate an RSA key pair (once):
 *      openssl genrsa -out license-private.pem 2048
 *      openssl rsa -in license-private.pem -pubout -out license-public.pem
 *
 *   2. Copy the PUBLIC key content into app/Services/LicenseService.php (PUBLIC_KEY constant)
 *
 *   3. Generate a license:
 *      php scripts/generate-license.php license-private.pem clubcep.eu 500 2027-12-31
 *
 *   4. Paste the output into Admin → Settings → License tab
 */
if ($argc < 4) {
    echo "Usage: php generate-license.php <private-key.pem> <domain> <max_members> [expires YYYY-MM-DD]\n";
    echo "Example: php generate-license.php license-private.pem clubcep.eu 500 2027-12-31\n";
    exit(1);
}

$keyFile = $argv[1];
$domain = $argv[2];
$maxMembers = (int) $argv[3];
$expires = $argv[4] ?? date('Y-m-d', strtotime('+13 months'));

if (! file_exists($keyFile)) {
    echo "Error: Private key file not found: {$keyFile}\n";
    exit(1);
}

$privateKey = openssl_pkey_get_private(file_get_contents($keyFile));
if (! $privateKey) {
    echo "Error: Invalid private key\n";
    exit(1);
}

$payload = json_encode([
    'domain' => $domain,
    'max_members' => $maxMembers,
    'expires' => $expires,
    'issued_at' => date('Y-m-d H:i:s'),
]);

openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

$license = base64_encode($payload).'.'.base64_encode($signature);

echo "License Key:\n";
echo $license."\n\n";
echo "Payload: {$payload}\n";
echo "Valid for: {$domain}, up to {$maxMembers} members, expires {$expires}\n";
