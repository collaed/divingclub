<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ScheduleHeartbeat
{
    /** Record a successful task run. */
    public static function beat(string $task, ?string $message = null, ?int $durationMs = null): void
    {
        DB::table('schedule_heartbeats')->upsert([
            'task' => $task,
            'last_run_at' => now(),
            'success' => true,
            'message' => $message,
            'duration_ms' => $durationMs,
        ], ['task'], ['last_run_at', 'success', 'message', 'duration_ms']);
    }

    /** Record a failed task run. */
    public static function fail(string $task, string $message): void
    {
        DB::table('schedule_heartbeats')->upsert([
            'task' => $task,
            'last_run_at' => now(),
            'success' => false,
            'message' => $message,
            'duration_ms' => null,
        ], ['task'], ['last_run_at', 'success', 'message', 'duration_ms']);
    }

    /** Get all heartbeats for the dashboard. */
    public static function all(): array
    {
        return DB::table('schedule_heartbeats')->orderBy('task')->get()->all();
    }
}
