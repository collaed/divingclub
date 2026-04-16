<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessIncomingFiles extends Command
{
    protected $signature = 'incoming:process {--dry-run : Show matches without moving files}';

    protected $description = 'Match files in storage/app/incoming to members by name, move to their profile documents';

    public function handle(): int
    {
        $dir = storage_path('app/incoming');
        if (! is_dir($dir)) {
            $this->error("Incoming folder not found: {$dir}");

            return self::FAILURE;
        }

        $files = array_diff(scandir($dir), ['.', '..', '.gitkeep', 'processing.log']);
        $this->info(count($files).' files found in incoming/');

        $log = [];
        $matched = 0;
        $unmatched = 0;
        $memberMap = $this->buildMemberMap();

        foreach ($files as $file) {
            $fullPath = "{$dir}/{$file}";
            if (! is_file($fullPath)) {
                continue;
            }

            $result = $this->matchFile($file, $memberMap);

            if ($result['user']) {
                $user = $result['user'];
                $category = $this->guessCategory($file);
                $log[] = "✅ {$file} → {$user->detail->first_name} {$user->detail->last_name} (id={$user->id}) [{$category}] matched by: {$result['method']}";

                if (! $this->option('dry-run')) {
                    $this->importFile($fullPath, $file, $user, $category);
                    unlink($fullPath);
                }
                $matched++;
            } else {
                $log[] = "❌ {$file} → NO MATCH (candidates: {$result['candidates']})";
                $unmatched++;
            }
        }

        // Write log
        $logContent = 'Processing run: '.now()->toIso8601String()."\n"
            ."Matched: {$matched}, Unmatched: {$unmatched}\n"
            .str_repeat('-', 60)."\n"
            .implode("\n", $log)."\n\n";

        file_put_contents("{$dir}/processing.log", $logContent, FILE_APPEND);

        foreach ($log as $line) {
            $this->line($line);
        }

        $this->info("Done: {$matched} matched, {$unmatched} unmatched. See incoming/processing.log");

        return self::SUCCESS;
    }

    private function matchFile(string $filename, array $memberMap): array
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        // Remove common suffixes: dates, numbers, underscores
        $clean = preg_replace('/[_\-]\d{4,}.*$/', '', $name);
        $clean = preg_replace('/\s*\(\d+\)$/', '', $clean);
        $clean = trim($clean);

        // Normalize: remove accents, lowercase
        $normalized = $this->normalize($clean);

        // Try exact match
        if (isset($memberMap[$normalized])) {
            return ['user' => $memberMap[$normalized], 'method' => 'exact', 'candidates' => ''];
        }

        // Try reversed (LASTNAME Firstname → Firstname LASTNAME)
        $parts = preg_split('/[\s_]+/', $clean);
        if (count($parts) >= 2) {
            $reversed = $this->normalize(end($parts).' '.implode(' ', array_slice($parts, 0, -1)));
            if (isset($memberMap[$reversed])) {
                return ['user' => $memberMap[$reversed], 'method' => 'reversed', 'candidates' => ''];
            }
        }

        // Try partial match (filename contains member name)
        $bestMatch = null;
        $bestLen = 0;
        foreach ($memberMap as $key => $user) {
            if (strlen($key) > 4 && str_contains($normalized, $key) && strlen($key) > $bestLen) {
                $bestMatch = $user;
                $bestLen = strlen($key);
            }
        }
        if ($bestMatch) {
            return ['user' => $bestMatch, 'method' => 'partial', 'candidates' => ''];
        }

        // No match — show closest candidates
        $candidates = [];
        foreach ($memberMap as $key => $user) {
            $dist = levenshtein($normalized, $key);
            if ($dist <= 3) {
                $candidates[] = "{$user->detail->first_name} {$user->detail->last_name} (dist={$dist})";
            }
        }

        return ['user' => null, 'method' => '', 'candidates' => implode(', ', array_slice($candidates, 0, 3)) ?: 'none'];
    }

    private function importFile(string $fullPath, string $filename, User $user, string $category): void
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $safeName = Str::slug($user->detail->last_name.'-'.$user->detail->first_name).'-'.$category.'-'.now()->format('Ymd').'.'.$ext;
        $storagePath = "private/members/{$user->id}/{$safeName}";

        $destDir = storage_path("app/private/members/{$user->id}");
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        copy($fullPath, storage_path("app/{$storagePath}"));

        Document::create([
            'user_id' => $user->id,
            'category' => $category,
            'file_path' => $storagePath,
            'original_filename' => $filename,
            'mime_type' => mime_content_type($fullPath) ?: 'application/octet-stream',
            'size_bytes' => filesize($fullPath),
            'is_current' => $category === 'licence_card',
        ]);
    }

    private function guessCategory(string $filename): string
    {
        $lower = mb_strtolower($filename);
        if (str_contains($lower, 'licence') || str_contains($lower, 'flassa') || str_contains($lower, 'card')) {
            return 'licence_card';
        }
        if (str_contains($lower, 'medical') || str_contains($lower, 'certif') || str_contains($lower, 'visite')) {
            return 'medical';
        }
        if (str_contains($lower, 'assurance') || str_contains($lower, 'insurance')) {
            return 'insurance';
        }

        return 'other';
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(['_', '-', '.'], ' ', $s);
        $s = Str::ascii($s);
        $s = preg_replace('/\s+/', ' ', $s);

        return $s;
    }

    private function buildMemberMap(): array
    {
        $map = [];
        foreach (MemberDetail::with('user')->get() as $d) {
            if (! $d->user) {
                continue;
            }
            $fn = $d->first_name ?? '';
            $ln = $d->last_name ?? '';
            $map[$this->normalize("{$fn} {$ln}")] = $d->user;
            $map[$this->normalize("{$ln} {$fn}")] = $d->user;
        }

        return $map;
    }
}
