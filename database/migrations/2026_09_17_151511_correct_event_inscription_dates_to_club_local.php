<?php

declare(strict_types=1);

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time correction: inscription_open_at/inscription_close_at were
     * always entered as club-local wall-clock time via a plain
     * datetime-local input (no timezone) but stored as if already UTC.
     * Reinterprets each existing value's raw digits as club-local and
     * rewrites the row with the correct UTC instant, so reading it back
     * through the now-fixed display code (UTC -> config('club.timezone'))
     * shows the same wall-clock numbers as before — only the underlying
     * instant, which now()-based comparisons (event automation,
     * registration status) rely on, actually changes.
     */
    public function up(): void
    {
        $this->convert(fn (Carbon $stored): string => Carbon::createFromFormat(
            'Y-m-d H:i:s', $stored->format('Y-m-d H:i:s'), config('club.timezone')
        )->utc()->format('Y-m-d H:i:s'));
    }

    public function down(): void
    {
        $this->convert(fn (Carbon $stored): string => $stored->copy()
            ->setTimezone(config('club.timezone'))->format('Y-m-d H:i:s'));
    }

    private function convert(callable $transform): void
    {
        foreach (['inscription_open_at', 'inscription_close_at'] as $column) {
            Event::query()->whereNotNull($column)->select(['id', $column])->orderBy('id')->get()
                ->each(function (Event $event) use ($column, $transform): void {
                    DB::table('events')->where('id', $event->id)->update([$column => $transform($event->$column)]);
                });
        }
    }
};
