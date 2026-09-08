<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PageVisit;
use Illuminate\Console\Command;

class PrunePageVisits extends Command
{
    protected $signature = 'tracking:prune';

    protected $description = 'Delete page_visits rows older than tracking.retention_days';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('tracking.retention_days', 3));
        $deleted = PageVisit::query()->where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} page visit(s) older than {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
