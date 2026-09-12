<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CloudflareAiUsageStat;
use App\Services\CloudflareUsageService;
use Illuminate\Console\Command;

class SyncCloudflareUsage extends Command
{
    protected $signature = 'cloudflare:sync-usage';

    protected $description = 'Pull recent Workers AI neuron usage from Cloudflare and store it for the dashboard history chart';

    public function handle(CloudflareUsageService $service): int
    {
        // Look back a few days, not just yesterday, in case analytics data
        // arrives with a delay or a scheduled run was missed.
        $rows = $service->fetchDailyUsage(today()->subDays(3), today());

        foreach ($rows as $row) {
            CloudflareAiUsageStat::updateOrCreate(
                ['date' => $row['date'], 'model_id' => $row['model_id']],
                ['neurons' => $row['neurons'], 'requests' => $row['requests']],
            );
        }

        $this->info(count($rows).' usage row(s) synced.');

        return self::SUCCESS;
    }
}
