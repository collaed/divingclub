<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $healthy = true;
        $start = microtime(true);

        // 1. Database connectivity + response time
        try {
            $dbStart = microtime(true);
            DB::select('SELECT 1');
            $dbMs = round((microtime(true) - $dbStart) * 1000);
            $checks['database'] = ['status' => 'ok', 'ms' => $dbMs];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $healthy = false;
        }

        // 2. Database size
        try {
            $driver = config('database.default');
            if ($driver === 'pgsql') {
                $dbName = config('database.connections.pgsql.database');
                $row = DB::selectOne('SELECT pg_database_size(?) as size', [$dbName]);
                $checks['db_size'] = ['status' => 'ok', 'bytes' => (int) $row->size, 'human' => $this->human($row->size)];
            } else {
                $row = DB::selectOne('SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = DATABASE()');
                $checks['db_size'] = ['status' => 'ok', 'bytes' => (int) $row->size, 'human' => $this->human($row->size)];
            }
        } catch (\Throwable $e) {
            $checks['db_size'] = ['status' => 'warn', 'error' => $e->getMessage()];
        }

        // 3. Disk space
        $storagePath = storage_path();
        $free = disk_free_space($storagePath);
        $total = disk_total_space($storagePath);
        $usedPct = round((1 - $free / $total) * 100);
        $checks['disk'] = [
            'status' => $usedPct > 95 ? 'fail' : ($usedPct > 85 ? 'warn' : 'ok'),
            'free' => $this->human($free),
            'total' => $this->human($total),
            'used_pct' => $usedPct,
        ];
        if ($usedPct > 95) {
            $healthy = false;
        }

        // 4. Storage writable
        try {
            $testFile = storage_path('app/.health_check');
            file_put_contents($testFile, 'ok');
            unlink($testFile);
            $checks['storage_writable'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['storage_writable'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $healthy = false;
        }

        // 5. Cache
        try {
            Cache::put('health_check', true, 10);
            $checks['cache'] = Cache::get('health_check') ? ['status' => 'ok'] : ['status' => 'fail'];
            Cache::forget('health_check');
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'warn', 'error' => $e->getMessage()];
        }

        // 6. Queue (pending jobs)
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            $checks['queue'] = [
                'status' => $failed > 10 ? 'warn' : 'ok',
                'pending' => $pending,
                'failed' => $failed,
            ];
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'warn', 'error' => $e->getMessage()];
        }

        // 7. App response time (total)
        $totalMs = round((microtime(true) - $start) * 1000);

        $hasWarning = collect($checks)->contains(fn ($c): bool => ($c['status'] ?? '') === 'warn');
        $issues = [];
        foreach ($checks as $name => $check) {
            if (! in_array($check['status'], ['warn', 'fail'])) {
                continue;
            }
            $detail = match (true) {
                isset($check['error']) => $check['error'],
                isset($check['used_pct']) => $check['used_pct'].'% used',
                isset($check['failed']) => $check['failed'].' failed jobs',
                default => '',
            };
            $issues[] = strtoupper($name).': '.$check['status'].($detail ? " ($detail)" : '');
        }
        $status = $healthy ? ($hasWarning ? 'degraded' : 'healthy') : 'unhealthy';
        $code = $healthy ? ($hasWarning ? 299 : 200) : 503;

        return response()->json([
            'status' => $status,
            'message' => $issues ? implode(' | ', $issues) : 'All systems operational',
            'timestamp' => now()->toIso8601String(),
            'response_ms' => $totalMs,
            'checks' => $checks,
        ], $code);
    }

    private function human(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 4) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
