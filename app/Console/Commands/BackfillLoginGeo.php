<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LoginRecord;
use App\Support\GeoLocator;
use Illuminate\Console\Command;

class BackfillLoginGeo extends Command
{
    protected $signature = 'geo:backfill-login-records {--limit=2000}';

    protected $description = 'Fill country_code on existing login_records without one (rate-limit friendly)';

    public function handle(GeoLocator $geo): int
    {
        $rows = LoginRecord::query()
            ->whereNull('country_code')
            ->whereNotNull('ip_address')
            ->orderByDesc('created_at')
            ->limit((int) $this->option('limit'))
            ->get(['id', 'ip_address']);

        $filled = 0;
        foreach ($rows as $row) {
            $wasCached = cache()->has('geo:country:'.$row->ip_address);
            $c = $geo->country($row->ip_address);
            if ($c['code'] !== null) {
                $row->update(['country_code' => $c['code'], 'country_name' => $c['name']]);
                $filled++;
            }
            if (! $wasCached) {
                usleep(1_200_000); // stay well under the free tier's rate limit
            }
        }

        $this->info("Backfilled {$filled} of {$rows->count()} login record(s).");

        return self::SUCCESS;
    }
}
