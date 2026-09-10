<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DocumentDispatchOpen;
use App\Support\GeoLocator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class ResolveDocOpenGeo implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $openId) {}

    public function handle(GeoLocator $geo): void
    {
        $open = DocumentDispatchOpen::find($this->openId);
        if (! $open || $open->country_code !== null) {
            return;
        }

        $c = $geo->country($open->ip_address);
        if ($c['code'] !== null) {
            $open->update(['country_code' => $c['code']]);
        }
    }
}
