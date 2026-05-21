<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateService
{
    /** Current app version — bump this on each release. */
    public const VERSION = '1.1.0';

    /** GitHub repo for update checks. */
    private const REPO = 'collaed/divingclub';

    /** Cache TTL for version check (6 hours). */
    private const CHECK_TTL = 21600;

    /** Check GitHub for the latest release tag. */
    public static function checkForUpdate(): ?array
    {
        return Cache::remember('app_update_check', self::CHECK_TTL, function () {
            try {
                $response = Http::timeout(10)
                    ->get('https://api.github.com/repos/'.self::REPO.'/releases/latest');

                if (! $response->ok()) {
                    // Fall back to tags if no releases
                    $response = Http::timeout(10)
                        ->get('https://api.github.com/repos/'.self::REPO.'/tags');

                    if (! $response->ok() || empty($response->json())) {
                        return;
                    }

                    $latest = $response->json()[0]['name'] ?? null;
                    $notes = null;
                    $url = 'https://github.com/'.self::REPO;
                } else {
                    $data = $response->json();
                    $latest = $data['tag_name'] ?? null;
                    $notes = $data['body'] ?? null;
                    $url = $data['html_url'] ?? 'https://github.com/'.self::REPO;
                }

                if (! $latest) {
                    return;
                }

                // Normalize version (strip 'v' prefix)
                $latestClean = ltrim($latest, 'v');
                $hasUpdate = version_compare($latestClean, self::VERSION, '>');

                return [
                    'current' => self::VERSION,
                    'latest' => $latestClean,
                    'tag' => $latest,
                    'has_update' => $hasUpdate,
                    'release_notes' => $notes,
                    'url' => $url,
                    'checked_at' => now()->toIso8601String(),
                ];
            } catch (\Throwable $e) {
                Log::warning("Update check failed: {$e->getMessage()}");

                return;
            }
        });
    }

    /** Apply update: git pull + composer install + migrate + cache clear. */
    public static function applyUpdate(): array
    {
        $steps = [];
        $basePath = escapeshellarg(base_path());

        // 1. Git pull
        $output = self::run("cd {$basePath} && git pull origin main 2>&1");
        $steps['git_pull'] = $output;

        if (str_contains($output, 'Already up to date')) {
            return ['success' => true, 'message' => 'Already up to date.', 'steps' => $steps];
        }

        // 2. Composer install (no-dev)
        $steps['composer'] = self::run("cd {$basePath} && composer install --no-dev --optimize-autoloader --no-interaction 2>&1");

        // 3. NPM build (if package.json changed)
        if (str_contains($steps['git_pull'], 'package.json') || str_contains($steps['git_pull'], 'package-lock.json')) {
            $steps['npm'] = self::run("cd {$basePath} && npm ci && npm run build 2>&1");
        }

        // 4. Migrate
        $steps['migrate'] = self::run("cd {$basePath} && php artisan migrate --force 2>&1");

        // 5. Clear caches
        $steps['cache'] = self::run("cd {$basePath} && php artisan optimize:clear 2>&1");

        // 6. Restart Horizon if running
        $steps['horizon'] = self::run("cd {$basePath} && php artisan horizon:terminate 2>&1");

        // Flush update cache
        Cache::forget('app_update_check');

        Log::info('Update applied', $steps);

        return ['success' => true, 'message' => 'Update applied successfully.', 'steps' => $steps];
    }

    /** Get current git commit info. */
    public static function currentCommit(): array
    {
        $basePath = base_path();

        return [
            'hash' => trim(shell_exec('cd '.escapeshellarg($basePath).' && git rev-parse --short HEAD 2>/dev/null') ?? ''),
            'date' => trim(shell_exec('cd '.escapeshellarg($basePath).' && git log -1 --format=%ci 2>/dev/null') ?? ''),
            'message' => trim(shell_exec('cd '.escapeshellarg($basePath).' && git log -1 --format=%s 2>/dev/null') ?? ''),
            'branch' => trim(shell_exec('cd '.escapeshellarg($basePath).' && git rev-parse --abbrev-ref HEAD 2>/dev/null') ?? ''),
        ];
    }

    private static function run(string $cmd): string
    {
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        return implode("\n", $output);
    }
}
