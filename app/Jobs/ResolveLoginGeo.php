<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LoginRecord;
use App\Support\GeoLocator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fill a login record's country off its IP, out of band so login stays fast.
 */
class ResolveLoginGeo implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $loginRecordId) {}

    public function handle(GeoLocator $geo): void
    {
        $record = LoginRecord::find($this->loginRecordId);
        if (! $record || $record->country_code !== null) {
            return;
        }

        $c = $geo->country($record->ip_address);
        if ($c['code'] !== null) {
            $record->update(['country_code' => $c['code'], 'country_name' => $c['name']]);
        }
    }
}
