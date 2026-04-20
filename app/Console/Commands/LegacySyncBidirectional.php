<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\MemberDetail;
use App\Models\User;
use App\Services\ScheduleHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LegacySyncBidirectional extends Command
{
    protected $signature = 'legacy:sync
        {--import-only : Skip export phase}
        {--export-only : Skip import phase}
        {--dry-run : Show what would be synced without writing}';

    protected $description = 'Bidirectional sync between legacy Joomla MySQL and new system (direct DB access)';

    /** @var array<int, string> Instructor initial mapping: legacy userid => initial letter */
    private array $instructorMap = [
        307 => 'G', 290 => 'L', 351 => 'E', 66 => 'M', 253 => 'O',
        250 => 'V', 265 => 'K', 328 => 'T', 330 => 'B', 109 => 'P',
        184 => 'N', 261 => 'J', 206 => 'S', 129 => 'U', 246 => 'F',
        317 => 'A', 324 => 'C',
    ];

    private int $syncRunId = 0;

    /** @var array<string, int> */
    private array $counts = [];

    public function handle(): int
    {
        $start = microtime(true);

        if (! $this->hasLegacyConnection()) {
            $this->error('Legacy database connection not available. Set LEGACY_DB_* in .env');

            return self::FAILURE;
        }

        $this->syncRunId = (int) DB::table('sync_runs')->insertGetId([
            'started_at' => now(),
            'status' => 'running',
        ]);

        $dryRun = (bool) $this->option('dry-run');

        try {
            if (! $this->option('import-only')) {
                $this->exportToLegacy($dryRun);
            }

            if (! $this->option('export-only')) {
                $this->importMembers($dryRun);
                $this->importEvents($dryRun);
                $this->importRegistrations($dryRun);
                $this->importInstructorAvailabilities($dryRun);
                $this->importEquipment($dryRun);
            }

            $runtime = microtime(true) - $start;
            ScheduleHeartbeat::beat('legacy-sync-bidi', json_encode($this->counts));
            $this->finishRun('ok');

            $this->info(($dryRun ? '[DRY RUN] ' : '').'Sync complete in '.round($runtime, 2).'s');
            $this->table(['Table', 'Count'], collect($this->counts)->map(fn ($v, $k) => [$k, $v])->values()->toArray());

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->finishRun('error', $e->getMessage());
            Log::error('Legacy bidirectional sync failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function hasLegacyConnection(): bool
    {
        try {
            DB::connection('legacy')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ─── PHASE 1: EXPORT (New → Old) ────────────────────────────────────

    private function exportToLegacy(bool $dryRun): void
    {
        $this->info('Phase 1: Export (New → Old)');

        $lastRun = DB::table('sync_runs')
            ->where('id', '<', $this->syncRunId)
            ->where('status', 'ok')
            ->orderByDesc('started_at')
            ->value('started_at');

        if (! $lastRun) {
            $this->info('  No previous sync run — skipping export.');
            $this->counts['exported_members'] = 0;
            $this->counts['exported_events'] = 0;

            return;
        }

        $this->exportMembers($lastRun, $dryRun);
        $this->exportEvents($lastRun, $dryRun);
    }

    private function exportMembers(string $lastRun, bool $dryRun): void
    {
        $modified = DB::table('member_details')
            ->join('users', 'member_details.user_id', '=', 'users.id')
            ->where('member_details.locally_modified_at', '>', $lastRun)
            ->get();

        $count = 0;
        foreach ($modified as $row) {
            // Find the legacy user by email match
            $legacyUser = DB::connection('legacy')
                ->table('jos_users')
                ->where('email', $row->primary_email)
                ->first();

            if (! $legacyUser) {
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY] Would export member: {$row->primary_email}");
                $count++;

                continue;
            }

            DB::connection('legacy')->table('jos_comprofiler')
                ->where('id', $legacyUser->id)
                ->update(array_filter([
                    'firstname' => $row->first_name,
                    'lastname' => $row->last_name,
                    'cb_datenaissance' => $row->date_of_birth,
                    'cb_telpri' => $row->phone_private,
                    'cb_teloff' => $row->phone_office,
                    'cb_telgsm' => $row->phone_mobile,
                    'cb_country' => $row->country,
                    'cb_sexe' => $row->sex === 'M' ? 'Homme' : ($row->sex === 'F' ? 'Femme' : null),
                    'cb_peracc' => $row->emergency_contact_name,
                ], fn ($v) => $v !== null));

            // Convert cotisation_years back to space-separated string
            $cotisYears = json_decode($row->cotisation_years ?? '[]', true);
            if (is_array($cotisYears) && count($cotisYears) > 0) {
                DB::connection('legacy')->table('jos_comprofiler')
                    ->where('id', $legacyUser->id)
                    ->update(['cb_cotis' => implode(' ', $cotisYears)]);
            }

            DB::table('member_details')->where('id', $row->id)->update(['locally_modified_at' => null]);
            $count++;
        }

        $this->counts['exported_members'] = $count;
    }

    private function exportEvents(string $lastRun, bool $dryRun): void
    {
        $modified = Event::where('locally_modified_at', '>', $lastRun)->get();
        $count = 0;

        foreach ($modified as $event) {
            $legacyData = [
                'dat' => $event->event_date?->format('Y-m-d'),
                'heure' => $event->event_time,
                'titre' => $event->title,
                'Lieu' => $event->location,
                'heuref' => $event->end_time,
                'datef' => $event->end_date?->format('Y-m-d'),
                'descr' => $event->description,
                'clot' => $event->inscriptions_closed ? '1' : '0',
                'max' => $event->max_participants,
            ];

            if ($dryRun) {
                $this->line("  [DRY] Would export event: {$event->title}");
                $count++;

                continue;
            }

            if ($event->joomla_sortie_id) {
                DB::connection('legacy')->table('sorties')
                    ->where('id', $event->joomla_sortie_id)
                    ->update($legacyData);
            } else {
                $legacyId = DB::connection('legacy')->table('sorties')->insertGetId($legacyData);
                $event->update(['joomla_sortie_id' => $legacyId, 'locally_modified_at' => null]);
            }

            $event->update(['locally_modified_at' => null]);
            $count++;
        }

        $this->counts['exported_events'] = $count;
    }

    // ─── PHASE 2: IMPORT (Old → New) ────────────────────────────────────

    private function importMembers(bool $dryRun): void
    {
        $this->info('Phase 2: Import (Old → New)');

        $legacyUsers = DB::connection('legacy')
            ->table('jos_users')
            ->join('jos_comprofiler', 'jos_users.id', '=', 'jos_comprofiler.id')
            ->where('jos_users.block', 0)
            ->get();

        $imported = 0;
        $skipped = 0;

        foreach ($legacyUsers as $lu) {
            // Find existing user by email
            $user = User::where('primary_email', $lu->email)->first();

            // Skip if locally modified
            if ($user) {
                $detail = $user->detail;
                if ($detail && $detail->locally_modified_at && $detail->synced_at
                    && $detail->locally_modified_at > $detail->synced_at) {
                    $skipped++;

                    continue;
                }
            }

            if ($dryRun) {
                $this->line('  [DRY] Would import member: '.$lu->email);
                $imported++;

                continue;
            }

            if (! $user) {
                // Don't auto-create — the existing sync:old-events handles member creation
                $skipped++;

                continue;
            }

            // Update member details
            $cotisYears = $lu->cb_cotis ? array_values(array_filter(explode(' ', trim($lu->cb_cotis)))) : [];

            $detail = $user->detail ?? new MemberDetail(['user_id' => $user->id]);
            $detail->fill([
                'first_name' => $lu->firstname ?: $detail->first_name,
                'last_name' => $lu->lastname ?: $detail->last_name,
                'birth_name' => $lu->cb_nomdenaissance ?? $lu->cb_famnam ?? $detail->birth_name,
                'sex' => ($lu->cb_sexe ?? '') === 'Homme' ? 'M' : (($lu->cb_sexe ?? '') === 'Femme' ? 'F' : $detail->sex),
                'date_of_birth' => $this->parseDate($lu->cb_datenaissance ?? null) ?? $detail->date_of_birth,
                'phone_private' => $lu->cb_telpri ?? $detail->phone_private,
                'phone_office' => $lu->cb_teloff ?? $detail->phone_office,
                'phone_mobile' => $lu->cb_telgsm ?? $detail->phone_mobile,
                'country' => $lu->cb_country ?? $detail->country,
                'emergency_contact_name' => $lu->cb_peracc ?? $detail->emergency_contact_name,
                'cotisation_years' => $cotisYears,
                'instructor_initial' => $this->instructorMap[$lu->id] ?? $detail->instructor_initial,
                'synced_at' => now(),
            ]);
            $detail->save();

            $imported++;
        }

        $this->counts['imported_members'] = $imported;
        $this->counts['skipped_members'] = $skipped;
    }

    private function importEvents(bool $dryRun): void
    {
        $legacyEvents = DB::connection('legacy')
            ->table('sorties')
            ->where('dat', '>=', now()->subYear()->format('Y-m-d'))
            ->orderBy('id')
            ->get();

        $imported = 0;
        foreach ($legacyEvents as $le) {
            $existing = Event::where('joomla_sortie_id', $le->id)->first();

            // Skip if locally modified
            if ($existing && $existing->locally_modified_at && $existing->synced_at
                && $existing->locally_modified_at > $existing->synced_at) {
                continue;
            }

            if ($dryRun) {
                $this->line('  [DRY] Would import event: '.($le->titre ?? 'untitled'));
                $imported++;

                continue;
            }

            $data = [
                'title' => $le->titre ?: 'Untitled',
                'event_date' => $le->dat,
                'event_time' => $le->heure ?: null,
                'end_time' => $le->heuref ?: null,
                'end_date' => $this->parseDate($le->datef ?? null),
                'location' => $le->Lieu ?: null,
                'description' => $le->descr ?: null,
                'inscription_open_at' => $this->parseDateTime($le->date_ouverture ?? null),
                'inscriptions_closed' => ($le->clot ?? '0') === '1',
                'max_participants' => $le->max ?: null,
                'joomla_sortie_id' => $le->id,
                'synced_at' => now(),
            ];

            if ($existing) {
                $existing->update($data);
            } else {
                Event::create($data);
            }
            $imported++;
        }

        $this->counts['imported_events'] = $imported;
    }

    private function importRegistrations(bool $dryRun): void
    {
        $legacyRegs = DB::connection('legacy')
            ->table('inscriptions')
            ->join('sorties', 'inscriptions.id_sortie', '=', 'sorties.id')
            ->where('sorties.dat', '>=', now()->subYear()->format('Y-m-d'))
            ->select('inscriptions.*')
            ->orderBy('inscriptions.cp')
            ->get();

        $eventMap = Event::whereNotNull('joomla_sortie_id')->pluck('id', 'joomla_sortie_id');
        $userMap = $this->buildLegacyUserMap();

        $imported = 0;
        foreach ($legacyRegs as $lr) {
            $eventId = $eventMap[$lr->id_sortie] ?? null;
            if (! $eventId) {
                continue;
            }

            $userId = $lr->userid ? ($userMap[$lr->userid] ?? null) : null;

            if ($dryRun) {
                $imported++;

                continue;
            }

            DB::table('event_registrations')->updateOrInsert(
                ['joomla_inscription_id' => $lr->cp],
                [
                    'event_id' => $eventId,
                    'user_id' => $userId,
                    'guest_name' => (! $userId && ($lr->nom ?? null)) ? $lr->nom : null,
                    'status' => ($lr->dat_desinsc ?? null) ? 'cancelled' : 'confirmed',
                    'registered_by' => $lr->autreid_insc ? ($userMap[$lr->autreid_insc] ?? null) : null,
                    'cancelled_by' => $lr->autreid_desinsc ? ($userMap[$lr->autreid_desinsc] ?? null) : null,
                    'comment' => $lr->com_insc ?: null,
                    'cancellation_comment' => $lr->com_desinsc ?: null,
                    'registered_at' => $lr->dat_insc,
                    'cancelled_at' => $lr->dat_desinsc,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
            $imported++;
        }

        $this->counts['imported_registrations'] = $imported;
    }

    private function importInstructorAvailabilities(bool $dryRun): void
    {
        if (! Schema::hasTable('instructor_availabilities')) {
            $this->counts['imported_instructor_availabilities'] = 0;

            return;
        }

        $legacyPiscines = DB::connection('legacy')->table('mon_piscines')->get();
        $userMap = $this->buildLegacyUserMap();

        $locationTypeMap = [
            'S' => 'pool_steinfort', 'M' => 'pool_merl',
            'V' => 'pool_vendredi', 'N' => 'pool_nuit',
        ];

        $imported = 0;
        foreach ($legacyPiscines as $lp) {
            $userId = $userMap[$lp->mon_id] ?? null;
            if (! $userId) {
                continue;
            }

            $parts = explode('-', $lp->id, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $activityType = $locationTypeMap[$parts[0]] ?? 'pool';
            $date = $this->parseLegacyDate($parts[1]);
            if (! $date) {
                continue;
            }

            if ($dryRun) {
                $imported++;

                continue;
            }

            DB::table('instructor_availabilities')->updateOrInsert(
                ['user_id' => $userId, 'date' => $date, 'activity_type' => $activityType],
                ['assigned_by' => $userMap[$lp->par] ?? null, 'updated_at' => now(), 'created_at' => DB::raw('COALESCE(created_at, NOW())')]
            );
            $imported++;
        }

        $this->counts['imported_instructor_availabilities'] = $imported;
    }

    private function importEquipment(bool $dryRun): void
    {
        $legacyMatos = DB::connection('legacy')->table('sm_matos')->get();
        $imported = 0;

        foreach ($legacyMatos as $lm) {
            $name = $lm->nom ?? '';
            $type = 'other';
            if (str_starts_with($name, 'Bloc')) {
                $type = 'tank';
            } elseif (str_starts_with($name, 'Stab')) {
                $type = 'bcd';
            } elseif (str_starts_with($name, 'Détendeur') || str_starts_with($name, 'Detendeur')) {
                $type = 'regulator';
            }

            $existing = DB::table('equipment')->where('name', $name)->first();
            if ($existing) {
                $imported++;

                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY] Would import equipment: {$name}");
                $imported++;

                continue;
            }

            DB::table('equipment')->insert([
                'name' => $name, 'type' => $type, 'status' => 'available',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $imported++;
        }

        $this->counts['imported_equipment'] = $imported;
    }

    // ─── HELPERS ────────────────────────────────────────────────────────

    /** @return array<int, int> Legacy user ID → new user ID */
    private function buildLegacyUserMap(): array
    {
        $legacyUsers = DB::connection('legacy')->table('jos_users')->select('id', 'email')->get();
        $newUsers = User::pluck('id', 'primary_email');
        $map = [];

        foreach ($legacyUsers as $lu) {
            if (isset($newUsers[$lu->email])) {
                $map[$lu->id] = $newUsers[$lu->email];
            }
        }

        return $map;
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value || $value === '0000-00-00' || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateTime(?string $value): ?string
    {
        if (! $value || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /** Parse "DD/MM/YY" → "YYYY-MM-DD" */
    private function parseLegacyDate(string $value): ?string
    {
        $parts = explode('/', $value);
        if (count($parts) !== 3) {
            return null;
        }

        $day = (int) $parts[0];
        $month = (int) $parts[1];
        $year = (int) $parts[2];
        $year = $year < 100 ? 2000 + $year : $year;

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function finishRun(string $status, ?string $error = null): void
    {
        DB::table('sync_runs')->where('id', $this->syncRunId)->update([
            'finished_at' => now(),
            'counts' => json_encode($this->counts),
            'status' => $status,
            'error' => $error,
        ]);
    }
}
