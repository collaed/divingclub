<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\MedicalComplianceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncOldEvents extends Command
{
    protected $signature = 'sync:old-events
        {--since= : Start date (YYYY-MM-DD), default: last sync or 2015-01-01}
        {--until= : End date (YYYY-MM-DD), default: +6 months from now}
        {--chunk=12 : Months per chunk for initial sync}
        {--dry-run : Show what would be synced without writing}';

    protected $description = 'Pull events and registrations from the old clubcep.eu system';

    private string $apiUrl;

    private string $apiKey;

    private int $syncedEvents = 0;

    private int $syncedRegs = 0;

    private int $skippedRegs = 0;

    public function handle(): int
    {
        $this->apiUrl = config('services.old_sync.url', 'https://clubcep.eu/wrapp/api_sync.php');
        $this->apiKey = config('services.old_sync.key', 'cep-sync-2026-hetzner');

        $since = $this->option('since')
            ?? $this->getLastSyncDate()
            ?? '2015-01-01';
        $until = $this->option('until')
            ?? now()->addMonths(6)->format('Y-m-d');
        $chunkMonths = (int) $this->option('chunk');

        $this->info("Syncing from {$since} to {$until} in {$chunkMonths}-month chunks");

        $cursor = Carbon::parse($since);
        $end = Carbon::parse($until);

        while ($cursor->lt($end)) {
            $chunkEnd = $cursor->copy()->addMonths($chunkMonths);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }

            $sinceStr = $cursor->format('Y-m-d');
            $untilStr = $chunkEnd->format('Y-m-d');

            $this->line("  Chunk: {$sinceStr} → {$untilStr}");

            $success = $this->syncChunk($sinceStr, $untilStr, $chunkMonths);

            if (! $success) {
                return self::FAILURE;
            }

            $cursor = $chunkEnd->addDay();
        }

        // Store last sync timestamp
        DB::table('theme_settings')->updateOrInsert(
            ['key' => 'joomla_last_sync'],
            ['value' => now()->toIso8601String()]
        );

        // Sync medical certs from VisitesMed
        $this->syncMedicalCerts();

        $this->info("Done: {$this->syncedEvents} events, {$this->syncedRegs} registrations synced, {$this->skippedRegs} skipped (no matching member)");

        return self::SUCCESS;
    }

    private function syncChunk(string $since, string $until, int $chunkMonths): bool
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['X-Sync-Key' => $this->apiKey])
                ->get($this->apiUrl, ['since' => $since, 'until' => $until]);
        } catch (\Exception $e) {
            if ($chunkMonths > 1) {
                $smaller = max(1, intdiv($chunkMonths, 2));
                $this->warn("    Timeout with {$chunkMonths}mo chunk, retrying with {$smaller}mo");

                $cursor = Carbon::parse($since);
                $end = Carbon::parse($until);
                while ($cursor->lt($end)) {
                    $subEnd = $cursor->copy()->addMonths($smaller);
                    if ($subEnd->gt($end)) {
                        $subEnd = $end->copy();
                    }
                    if (! $this->syncChunk($cursor->format('Y-m-d'), $subEnd->format('Y-m-d'), $smaller)) {
                        return false;
                    }
                    $cursor = $subEnd->addDay();
                }

                return true;
            }
            $this->error("    Failed: {$e->getMessage()}");
            Log::error('SyncOldEvents failed', ['since' => $since, 'until' => $until, 'error' => $e->getMessage()]);

            return false;
        }

        if (! $response->ok()) {
            $this->error("    HTTP {$response->status()}: {$response->body()}");

            return false;
        }

        $data = $response->json();
        $this->line("    Got {$data['sorties_count']} events, {$data['inscriptions_count']} registrations");

        if ($this->option('dry-run')) {
            return true;
        }

        // Build member lookup: "LASTNAME FIRSTNAME" → user_id
        $memberMap = $this->buildMemberMap();

        // Sync events
        foreach ($data['sorties'] as $s) {
            $this->upsertEvent($s);
        }

        // Sync registrations
        foreach ($data['inscriptions'] as $i) {
            $this->upsertRegistration($i, $memberMap);
        }

        return true;
    }

    private function upsertEvent(array $s): void
    {
        $eventType = $this->guessEventType($s['titre'] ?? '');

        $attrs = [
            'title' => $s['titre'] ?? 'Untitled',
            'event_date' => $s['dat'],
            'event_time' => $s['heure'] ?: null,
            'end_time' => $s['heuref'] ?: null,
            'end_date' => ($s['datef'] && $s['datef'] !== '0000-00-00') ? $s['datef'] : null,
            'location' => $s['Lieu'] ?: null,
            'description' => $s['descr'] ?: null,
            'max_participants' => ((int) ($s['max'] ?? 0)) ?: null,
            'inscriptions_closed' => in_array($s['clot'] ?? '', ['O', '1']),
            'inscription_open_at' => ($s['date_ouverture'] ?? '') && $s['date_ouverture'] !== '0000-00-00 00:00:00' ? $s['date_ouverture'] : null,
            'event_type' => $eventType,
            'status' => 'scheduled',
        ];

        $event = Event::updateOrCreate(
            ['joomla_sortie_id' => (int) $s['id']],
            $attrs
        );

        if ($event->wasRecentlyCreated) {
            $event->update(['created_by' => User::first()?->id ?? 1]);
        }

        $this->syncedEvents++;
    }

    private function upsertRegistration(array $i, array $memberMap): void
    {
        $event = Event::where('joomla_sortie_id', (int) $i['id_sortie'])->first();
        if (! $event) {
            return;
        }

        // Match member by name
        $name = $i['user_name'] ?? $i['nom'] ?? '';
        $userId = $memberMap[mb_strtolower(trim($name))] ?? null;

        if (! $userId && $i['userid']) {
            // Try by Joomla user ID via email
            $email = $i['user_email'] ?? null;
            if ($email) {
                $userId = User::where('primary_email', $email)->value('id');
            }
        }

        if (! $userId && $name && ($i['user_email'] ?? null)) {
            $userId = $this->autoCreateMember($name, $i['user_email']);
            if ($userId) {
                $memberMap[mb_strtolower(trim($name))] = $userId;
            }
        }

        if (! $userId) {
            $this->skippedRegs++;
            Log::info("SyncOldEvents: no match for '{$name}' (joomla uid={$i['userid']})");

            return;
        }

        $isCancelled = ! empty($i['dat_desinsc']) && $i['dat_desinsc'] !== '0000-00-00 00:00:00';

        // Match by event+user (Joomla can have multiple rows per user per event)
        EventRegistration::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $userId],
            [
                'joomla_inscription_id' => (int) ($i['cp'] ?? $i['id'] ?? 0),
                'status' => $isCancelled ? 'cancelled' : 'confirmed',
                'comment' => $i['com_insc'] ?: null,
                'created_at' => $i['dat_insc'] ? Carbon::parse($i['dat_insc']) : now(),
            ]
        );

        $this->syncedRegs++;
    }

    private function buildMemberMap(): array
    {
        $map = [];
        $details = MemberDetail::with('user')->get();
        foreach ($details as $d) {
            if (! $d->user) {
                continue;
            }
            // Map multiple name formats
            $full = mb_strtolower(trim($d->first_name.' '.$d->last_name));
            $reversed = mb_strtolower(trim($d->last_name.' '.$d->first_name));
            $joomla = mb_strtolower(trim($d->first_name.' '.mb_strtoupper($d->last_name)));
            $map[$full] = $d->user_id;
            $map[$reversed] = $d->user_id;
            $map[$joomla] = $d->user_id;
            // Also email
            $map[mb_strtolower($d->user->primary_email)] = $d->user_id;
        }

        return $map;
    }

    private function guessEventType(string $title): string
    {
        $t = mb_strtolower($title);
        if (str_contains($t, 'piscine') || str_contains($t, 'pool') || str_contains($t, 'merl')) {
            return 'pool';
        }
        if (str_contains($t, 'carrière') || str_contains($t, 'carriere') || str_contains($t, 'lac') || str_contains($t, 'plongée')) {
            return 'dive';
        }
        if (str_contains($t, 'théorie') || str_contains($t, 'theorie') || str_contains($t, 'cours')) {
            return 'theory';
        }
        if (str_contains($t, 'formation') || str_contains($t, 'training') || str_contains($t, 'apnée')) {
            return 'training';
        }

        return 'social';
    }

    private function autoCreateMember(string $name, string $email): ?int
    {
        // Parse "Firstname LASTNAME" or "Firstname Lastname"
        $parts = preg_split('/\s+/', trim($name), 2);
        $firstName = $parts[0] ?? $name;
        $lastName = $parts[1] ?? '';

        $user = User::create([
            'primary_email' => $email,
            'role_id' => Role::where('slug', 'member')->value('id') ?? 2,
            'status_id' => MemberStatus::where('slug', 'actif')->value('id') ?? 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('member');

        MemberDetail::create([
            'user_id' => $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        $this->info("    Auto-created member: {$firstName} {$lastName} ({$email})");

        return $user->id;
    }

    private function syncMedicalCerts(): void
    {
        $url = str_replace('api_sync.php', 'api_scancards.php', $this->apiUrl);
        $since = now()->subDays(30)->format('Y-m-d');

        try {
            $response = Http::timeout(30)->withHeaders(['X-Sync-Key' => $this->apiKey])
                ->get($url, ['since' => $since]);
        } catch (\Throwable) {
            return;
        }

        if (! $response->ok()) {
            return;
        }

        $data = $response->json();
        $imported = 0;

        foreach ($data['files'] ?? [] as $f) {
            if ($f['folder'] !== 'VisitesMed') {
                continue;
            }

            if (! preg_match('/^(.+?)\s+(\d+)\.(pdf|jpg|jpeg|png)$/i', $f['name'], $m)) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($m[1]), 2);
            $user = User::whereHas('detail', fn ($q) => $q->whereRaw('LOWER(first_name) = ?', [mb_strtolower($parts[0] ?? '')])->whereRaw('LOWER(last_name) = ?', [mb_strtolower($parts[1] ?? '')]))->first();
            if (! $user) {
                continue;
            }

            if (Document::where('user_id', $user->id)->where('original_filename', $f['name'])->where('size_bytes', $f['size'])->exists()) {
                continue;
            }

            $content = @file_get_contents($f['url'], false, stream_context_create(['http' => ['header' => 'X-Sync-Key: '.$this->apiKey]]));
            if (! $content) {
                continue;
            }

            $storagePath = "private/medical/{$user->id}/{$f['name']}";
            $dir = storage_path("app/private/medical/{$user->id}");
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents(storage_path("app/{$storagePath}"), $content);

            Document::where('user_id', $user->id)->where('category', 'medical')->where('is_current', true)->update(['is_current' => false]);
            $doc = Document::create([
                'user_id' => $user->id,
                'category' => 'medical',
                'file_path' => $storagePath,
                'original_filename' => $f['name'],
                'mime_type' => str_ends_with($f['name'], '.pdf') ? 'application/pdf' : 'image/jpeg',
                'size_bytes' => $f['size'],
                'is_current' => true,
                'date_established' => $f['modified'],
            ]);
            app(MedicalComplianceService::class)->evaluateCertificate($doc);
            $imported++;
        }

        if ($imported > 0) {
            $this->line("    Medical certs: {$imported} updated from VisitesMed");
        }
    }

    private function getLastSyncDate(): ?string
    {
        $val = DB::table('theme_settings')->where('key', 'joomla_last_sync')->value('value');
        if (! $val) {
            return null;
        }

        // For incremental: use events from 30 days before last sync (overlap for safety)
        return Carbon::parse($val)->subDays(30)->format('Y-m-d');
    }
}
