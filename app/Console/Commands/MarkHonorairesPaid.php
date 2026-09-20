<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Season;
use App\Services\MembershipRenewalService;
use Illuminate\Console\Command;

class MarkHonorairesPaid extends Command
{
    protected $signature = 'members:mark-honoraires-paid {--season= : Dues year, defaults to the current one}';

    protected $description = 'Mark honoraire members as paid for the season once its 1 October has passed';

    public function handle(MembershipRenewalService $renewals): int
    {
        $year = (string) ($this->option('season') ?: Season::currentDuesYear());
        $count = $renewals->markHonorairesPaid($year);

        $this->info("Honoraire members marked as paid for {$year}: {$count}");

        return self::SUCCESS;
    }
}
