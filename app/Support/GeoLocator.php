<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Best-effort IP → country. Free, no key (ipwho.is), cached 30 days per IP,
 * and never throws — geolocation is a nice-to-have, not load-bearing.
 */
class GeoLocator
{
    /**
     * @return array{code: ?string, name: ?string}
     */
    public function country(?string $ip): array
    {
        $none = ['code' => null, 'name' => null];

        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return $none;
        }

        return Cache::remember('geo:country:'.$ip, now()->addDays(30), function () use ($ip, $none): array {
            try {
                $res = Http::timeout(3)->retry(1, 200)
                    ->get("https://ipwho.is/{$ip}", ['fields' => 'success,country_code,country']);

                if ($res->ok() && $res->json('success') === true && $res->json('country_code')) {
                    return [
                        'code' => mb_strtoupper((string) $res->json('country_code')),
                        'name' => $res->json('country') ?: null,
                    ];
                }
            } catch (Throwable) {
                // fall through
            }

            return $none;
        });
    }

    /** ISO-3166 alpha-2 → flag emoji (regional indicator symbols). */
    public static function flag(?string $code): string
    {
        if ($code === null || strlen($code) !== 2 || ! ctype_alpha($code)) {
            return '';
        }

        $code = strtoupper($code);

        return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }
}
