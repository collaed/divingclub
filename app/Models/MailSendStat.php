<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per (date, provider): how many emails MailBalancer sent through
 * that provider that day. Feeds the "Email Sending Quota" history chart on
 * the admin dashboard — MailBalancer's own per-day counters live in cache
 * and expire at midnight, so this is the durable record.
 *
 * @property int $id
 * @property string $date
 * @property string $provider
 * @property int $count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MailSendStat extends Model
{
    protected $fillable = ['date', 'provider', 'count'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
