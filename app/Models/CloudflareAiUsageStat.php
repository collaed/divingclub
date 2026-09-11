<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per (date, model): Workers AI neuron/request usage for that
 * model that day, pulled from Cloudflare's GraphQL Analytics API by
 * SyncCloudflareUsage. Feeds the "Cloudflare AI Usage" history chart on the
 * admin dashboard, so the club can see when it's approaching its free
 * daily neuron allowance.
 *
 * @property int $id
 * @property string $date
 * @property string $model_id
 * @property int $neurons
 * @property int $requests
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CloudflareAiUsageStat extends Model
{
    protected $fillable = ['date', 'model_id', 'neurons', 'requests'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /**
     * Total neurons used per day for the last $days days, for the dashboard
     * history chart. Days with no synced data are included as zero.
     *
     * @return array{dates: list<string>, neurons: list<int>}
     */
    public static function history(int $days = 60): array
    {
        $since = today()->subDays($days - 1);
        $byDay = self::where('date', '>=', $since)->get()
            ->groupBy(fn (self $s) => $s->date->format('Y-m-d'));

        $dates = [];
        $neurons = [];
        for ($d = $since->copy(); $d->lte(today()); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $dates[] = $key;
            $neurons[] = (int) $byDay->get($key, collect())->sum('neurons');
        }

        return ['dates' => $dates, 'neurons' => $neurons];
    }
}
