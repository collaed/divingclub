<?php

/**
 * Legacy data import from the old Joomla/Community Builder system and Google Calendar.
 *
 * Migrates members, event registrations, payments, licences, and calendar events
 * from fulldata_clubcep.txt (TSV export from CB) and the club's public Google
 * Calendar iCal feed. Designed to run once during migration, with --dry-run and
 * granular --skip-* flags for safe incremental testing.
 *
 * @author  ClubCEP.eu
 *
 * @see     \App\Console\Commands\EnrichImportedEvents  — post-import enrichment
 * @see     fulldata_clubcep.txt                        — legacy TSV source
 */

namespace App\Console\Commands;

use App\Models\DiveSite;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberDetail;
use App\Models\MemberLicence;
use App\Models\PaymentExpected;
use App\Models\Season;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyData extends Command
{
    /*
    |--------------------------------------------------------------------------
    | Command Configuration
    |--------------------------------------------------------------------------
    | Flags allow selective import of each data type. Use --dry-run to preview
    | changes without committing to the database.
    */

    protected $signature = 'import:legacy
        {--source= : Path to fulldata_clubcep.txt}
        {--calendar-url= : Google Calendar iCal URL}
        {--skip-events : Skip Google Calendar import}
        {--skip-registrations : Skip event registration import}
        {--skip-payments : Skip payment import}
        {--skip-licences : Skip licence import}
        {--skip-member-updates : Skip member detail updates}
        {--dry-run : Show what would be imported without writing}';

    protected $description = 'Import legacy data from fulldata_clubcep.txt and Google Calendar';

    /*
    |--------------------------------------------------------------------------
    | In-memory Lookup Caches
    |--------------------------------------------------------------------------
    | Pre-built maps to avoid repeated DB queries during row-by-row processing.
    */

    private array $userEmailMap = [];

    private array $eventTitleDateMap = [];

    private array $diveSiteMap = [];

    private array $stats = [
        'calendar_events' => 0,
        'registrations' => 0,
        'payments' => 0,
        'licences' => 0,
        'member_updates' => 0,
        'training_updates' => 0,
        'skipped_registrations' => 0,
    ];

    public function handle(): int
    {
        $source = $this->option('source') ?: base_path('../fulldata_clubcep.txt');
        $calendarUrl = $this->option('calendar-url')
            ?: 'https://calendar.google.com/calendar/ical/clubcep%40gmail.com/public/basic.ics';
        $dryRun = $this->option('dry-run');

        if (! file_exists($source)) {
            $this->error("Source file not found: {$source}");

            return 1;
        }

        $this->buildUserEmailMap();
        $this->buildDiveSiteMap();

        $rows = $this->parseTsv($source);
        $this->info('Parsed '.count($rows).' rows from legacy data.');

        DB::beginTransaction();

        try {
            if (! $this->option('skip-events')) {
                $this->importCalendarEvents($calendarUrl, $dryRun);
            }

            if (! $this->option('skip-member-updates')) {
                $this->updateMemberDetails($rows, $dryRun);
            }

            if (! $this->option('skip-registrations')) {
                $this->importRegistrations($rows, $dryRun);
            }

            if (! $this->option('skip-payments')) {
                $this->importPayments($rows, $dryRun);
            }

            if (! $this->option('skip-licences')) {
                $this->importLicences($rows, $dryRun);
            }

            if ($dryRun) {
                DB::rollBack();
                $this->warn('DRY RUN — no changes written.');
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Import failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return 1;
        }

        $this->newLine();
        $this->info('=== Import Summary ===');
        foreach ($this->stats as $key => $val) {
            $this->line("  {$key}: {$val}");
        }

        return 0;
    }

    // ─── Parsing ───────────────────────────────────────────────

    private function parseTsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle, 0, "\t", '"');
        $rows = [];
        while (($row = fgetcsv($handle, 0, "\t", '"')) !== false) {
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }
        fclose($handle);

        return $rows;
    }

    private function buildUserEmailMap(): void
    {
        $this->userEmailMap = User::pluck('id', 'primary_email')
            ->mapWithKeys(fn ($id, $email) => [strtolower($email) => $id])
            ->toArray();
    }

    private function buildDiveSiteMap(): void
    {
        $this->diveSiteMap = DiveSite::pluck('id', 'name')->toArray();
    }

    private function findUserId(string $email): ?int
    {
        return $this->userEmailMap[strtolower(trim($email))] ?? null;
    }

    // ─── 1. Google Calendar Events ─────────────────────────────

    private function importCalendarEvents(string $url, bool $dryRun): void
    {
        $this->info('Importing Google Calendar events...');

        $ical = file_get_contents($url);
        if (! $ical) {
            $this->error('Failed to fetch calendar.');

            return;
        }

        $vevents = $this->parseIcal($ical);
        $this->info('  Found '.count($vevents).' calendar events.');

        $season = Season::first();
        $existingEvents = Event::pluck('id', DB::raw("CONCAT(title, '|', event_date)"))->toArray();

        $bar = $this->output->createProgressBar(count($vevents));
        foreach ($vevents as $ve) {
            $bar->advance();

            $title = $ve['summary'] ?? '';
            $date = $ve['date'] ?? null;
            if (! $title || ! $date) {
                continue;
            }

            // Skip "fermé" / closure notices
            if (preg_match('/ferm[ée]/i', $title)) {
                continue;
            }
            // Skip "Busy" placeholders
            if (strtolower(trim($title)) === 'busy') {
                continue;
            }

            $key = $title.'|'.$date;
            if (isset($existingEvents[$key])) {
                $this->eventTitleDateMap[$key] = $existingEvents[$key];

                continue;
            }

            $eventType = $this->classifyEventType($title);
            $location = $this->cleanIcalText($ve['location'] ?? '');
            $description = $this->cleanIcalText($ve['description'] ?? '');
            $diveSiteId = $this->matchDiveSite($title, $location);

            $seasonId = null;
            if ($season && $date >= $season->start_date && $date <= $season->end_date) {
                $seasonId = $season->id;
            }

            $event = Event::create([
                'title' => $title,
                'event_type' => $eventType,
                'event_date' => $date,
                'event_time' => $ve['time'] ?? null,
                'end_time' => $ve['end_time'] ?? null,
                'end_date' => $ve['end_date'] ?? null,
                'location' => $location ?: null,
                'description' => $description ?: null,
                'status' => $this->eventStatus($date, $title),
                'color_hex' => null,
                'max_participants' => null,
                'waiting_list_enabled' => false,
                'inscriptions_closed' => true,
                'levels_display' => false,
                'confirmation_required' => false,
                'is_federated' => false,
                'external_slots' => 0,
                'season_id' => $seasonId,
                'dive_site_id' => $diveSiteId,
            ]);

            $this->eventTitleDateMap[$key] = $event->id;
            $this->stats['calendar_events']++;
        }
        $bar->finish();
        $this->newLine();
        $this->info("  Imported {$this->stats['calendar_events']} new events.");
    }

    private function parseIcal(string $ical): array
    {
        $events = [];
        $blocks = explode('BEGIN:VEVENT', $ical);
        array_shift($blocks);

        foreach ($blocks as $block) {
            $block = str_replace("\r\n ", '', $block); // unfold lines
            $ev = [];

            foreach (explode("\n", $block) as $line) {
                $line = trim($line);
                if (str_starts_with($line, 'SUMMARY:')) {
                    $ev['summary'] = substr($line, 8);
                } elseif (str_starts_with($line, 'DTSTART:')) {
                    $dt = $this->parseIcalDate(substr($line, 8));
                    $ev['date'] = $dt['date'];
                    $ev['time'] = $dt['time'];
                } elseif (str_starts_with($line, 'DTSTART;VALUE=DATE:')) {
                    $ev['date'] = $this->parseIcalDateOnly(substr($line, 19));
                } elseif (str_starts_with($line, 'DTEND:')) {
                    $dt = $this->parseIcalDate(substr($line, 6));
                    $ev['end_date'] = $dt['date'];
                    $ev['end_time'] = $dt['time'];
                } elseif (str_starts_with($line, 'DTEND;VALUE=DATE:')) {
                    $ev['end_date'] = $this->parseIcalDateOnly(substr($line, 17));
                } elseif (str_starts_with($line, 'LOCATION:')) {
                    $ev['location'] = substr($line, 9);
                } elseif (str_starts_with($line, 'DESCRIPTION:')) {
                    $ev['description'] = substr($line, 12);
                }
            }

            if (isset($ev['date'])) {
                // If end_date equals date+1 and no time, it's a full-day event — use same date
                if (isset($ev['end_date']) && ! isset($ev['time']) && $ev['end_date'] !== $ev['date']) {
                    $ev['end_date'] = null;
                }
                $events[] = $ev;
            }
        }

        return $events;
    }

    private function parseIcalDate(string $val): array
    {
        // 20191114T170000Z or 20191114T170000
        $val = rtrim($val, 'Z');
        $date = substr($val, 0, 4).'-'.substr($val, 4, 2).'-'.substr($val, 6, 2);
        $time = null;
        if (strlen($val) >= 15) {
            $time = substr($val, 9, 2).':'.substr($val, 11, 2).':00';
        }

        return ['date' => $date, 'time' => $time];
    }

    private function parseIcalDateOnly(string $val): string
    {
        return substr($val, 0, 4).'-'.substr($val, 4, 2).'-'.substr($val, 6, 2);
    }

    private function cleanIcalText(string $text): string
    {
        $text = str_replace(['\\n', '\\,', '\\;'], ["\n", ',', ';'], $text);
        $text = preg_replace('/<[^>]+>/', '', $text); // strip HTML

        return trim($text);
    }

    /**
     * Classify event type from the Google Calendar title using French keywords.
     * The old system had no structured types — everything was free-text in French.
     */
    private function classifyEventType(string $title): string
    {
        $t = strtolower($title);
        if (preg_match('/piscine|steinfort|merl|ecolage/i', $t)) {
            return 'pool';
        }
        if (preg_match('/fosse|coque/i', $t)) {
            return 'pool';
        }
        if (preg_match('/nemo/i', $t)) {
            return 'pool';
        }
        if (preg_match('/apn[ée]e/i', $t)) {
            return 'training';
        }
        if (preg_match('/cours|th[ée]or|examen|formation|rifap|nitrox/i', $t)) {
            return 'theory';
        }
        if (preg_match('/lac|z[ée]lande|carri[èe]re|gravi[èe]re|plongée|sortie|todi|gombe|nettoyage/i', $t)) {
            return 'dive';
        }
        if (preg_match('/bbq|repas|no[ëe]l|barbecue|resto|accueil|assembl[ée]e|spillfest|50 ans/i', $t)) {
            return 'social';
        }
        if (preg_match('/juan|ustica|bonaire|oman|frejus|birmanie|portugal|tha[ïi]lande|egypte/i', $t)) {
            return 'trip';
        }
        if (preg_match('/r[ée]union/i', $t)) {
            return 'social';
        }

        return 'other';
    }

    private function eventStatus(string $date, string $title): string
    {
        $t = strtolower($title);
        if (str_contains($t, 'annul')) {
            return 'cancelled';
        }
        if ($date < now()->toDateString()) {
            return 'completed';
        }

        return 'scheduled';
    }

    /**
     * Match a dive site from the event title/location to our DiveSite records.
     * Maps common Luxembourg, Belgium, and Netherlands dive sites the club frequents.
     */
    private function matchDiveSite(string $title, string $location): ?int
    {
        $combined = strtolower($title.' '.$location);
        $mappings = [
            'lultzhausen' => 'Lac de la Haute-Sûre — Lultzhausen',
            'insenborn' => 'Lac de la Haute-Sûre — Insenborn',
            'nemo' => 'Nemo 33',
            'todi' => 'TODI',
            'gravière du fort' => 'Gravière du Fort',
            'vodelée' => 'Carrière de Vodelée',
            'floreffe' => 'Carrière de Floreffe',
            'rochefontaine' => 'Carrière de Rochefontaine',
            'dongelberg' => 'Carrière de Dongelberg',
            'barges' => 'Carrière de Barges',
            'dreischor' => 'Grevelingenmeer — Dreischor',
            'scharendijke' => 'Grevelingenmeer — Scharendijke',
            'wemeldinge' => 'Oosterschelde — Wemeldinge',
        ];

        foreach ($mappings as $keyword => $siteName) {
            if (str_contains($combined, $keyword)) {
                return $this->diveSiteMap[$siteName] ?? null;
            }
        }

        // Generic lake reference
        if (preg_match('/\blac\b/i', $combined) && ! str_contains($combined, 'insenborn')) {
            return $this->diveSiteMap['Lac de la Haute-Sûre — Lultzhausen'] ?? null;
        }

        return null;
    }

    // ─── 2. Member Detail Updates ──────────────────────────────

    private function updateMemberDetails(array $rows, bool $dryRun): void
    {
        $this->info('Updating member details from legacy data...');

        foreach ($rows as $row) {
            $email = trim($row['email'] ?? '');
            $userId = $this->findUserId($email);
            if (! $userId) {
                continue;
            }

            $detail = MemberDetail::where('user_id', $userId)->first();
            if (! $detail) {
                continue;
            }

            $updates = [];

            // Training enrollments
            $formations = trim($row['cb_formations'] ?? '');
            if ($formations) {
                $enrollments = array_filter(array_map('trim', explode('|*|', $formations)));
                if ($enrollments && (! $detail->training_enrollments || empty($detail->training_enrollments))) {
                    $updates['training_enrollments'] = $enrollments;
                }
            }

            // Other certifications
            $otherCerts = trim($row['cb_autbre'] ?? '');
            if ($otherCerts) {
                $certs = array_filter(array_map('trim', explode(';', $otherCerts)));
                if ($certs && (! $detail->other_certifications || empty($detail->other_certifications))) {
                    $updates['other_certifications'] = $certs;
                }
            }

            // Cotisation years
            $cotis = trim($row['cb_cotis'] ?? '');
            if ($cotis && (! $detail->cotisation_years || empty($detail->cotisation_years))) {
                $updates['cotisation_years'] = $this->parseCotisationYears($cotis);
            }

            // Dive count
            $diveCount = (int) ($row['cb_nbrplon2'] ?? 0);
            if ($diveCount > 0 && $detail->dive_count === 0) {
                $updates['dive_count'] = $diveCount;
            }

            // Brevet date
            $brevetDate = $this->parseLegacyDate($row['cb_datbre'] ?? '');
            if ($brevetDate && ! $detail->brevet_date) {
                $updates['brevet_date'] = $brevetDate;
            }

            // Birth name
            $birthName = trim($row['cb_nomdenaissance'] ?? '');
            if ($birthName && ! $detail->birth_name) {
                $updates['birth_name'] = $birthName;
            }

            if ($updates) {
                $detail->update($updates);
                $this->stats['member_updates']++;
            }
        }

        $this->info("  Updated {$this->stats['member_updates']} member details.");
    }

    /**
     * Parse the legacy cotisation (membership fee) year strings.
     * Old format used inconsistent patterns like "2009->2021-2022-2023".
     */
    private function parseCotisationYears(string $val): array
    {
        $years = [];
        // Handle patterns like "2009->2021-2022-2023" or "2009-2010"
        $val = preg_replace('/(\d{4})->/', '', $val); // remove "from" year in ranges
        foreach (preg_split('/[-,]/', $val) as $y) {
            $y = trim($y);
            if (preg_match('/^\d{4}$/', $y)) {
                $years[] = (int) $y;
            }
        }

        return array_unique($years);
    }

    // ─── 3. Event Registrations ────────────────────────────────

    private function importRegistrations(array $rows, bool $dryRun): void
    {
        $this->info('Importing event registrations from legacy inscriptions...');

        // Build event lookup from DB (title|date => id)
        $this->eventTitleDateMap = Event::pluck('id', DB::raw("CONCAT(title, '|', event_date)"))
            ->toArray();

        foreach ($rows as $row) {
            $email = trim($row['email'] ?? '');
            $userId = $this->findUserId($email);
            if (! $userId) {
                continue;
            }

            $inscriptions = trim($row['cb_inscriptions'] ?? '');
            if (! $inscriptions) {
                continue;
            }

            foreach (explode("\n", $inscriptions) as $line) {
                $line = trim($line);
                if (! $line || ! str_contains($line, ':')) {
                    continue;
                }

                $parts = explode(':', $line, 2);
                if (count($parts) !== 2 || ! str_contains($parts[0], '/')) {
                    continue;
                }

                $dateStr = trim($parts[0]);
                $eventTitle = trim($parts[1]);

                // Normalize the title — strip time patterns like "19h00-20h00"
                $eventTitle = $this->normalizeEventTitle($eventTitle);
                $date = $this->parseLegacyDate($dateStr);
                if (! $date) {
                    continue;
                }

                $eventId = $this->findEventId($eventTitle, $date);
                if (! $eventId) {
                    $this->stats['skipped_registrations']++;

                    continue;
                }

                // Avoid duplicates
                $exists = EventRegistration::where('event_id', $eventId)
                    ->where('user_id', $userId)
                    ->exists();
                if ($exists) {
                    continue;
                }

                EventRegistration::create([
                    'event_id' => $eventId,
                    'user_id' => $userId,
                    'status' => 'confirmed',
                ]);
                $this->stats['registrations']++;
            }
        }

        $this->info("  Imported {$this->stats['registrations']} registrations ({$this->stats['skipped_registrations']} skipped — no matching event).");
    }

    /**
     * Strip time patterns and attention notices that the old system appended to titles.
     */
    private function normalizeEventTitle(string $title): string
    {
        // Remove time patterns like "19h00-20h00", "19:00-20:00", "!!! attention..."
        $title = preg_replace('/\s*\d{1,2}[h:]\d{2}\s*[-–]\s*\d{1,2}[h:]\d{2}/', '', $title);
        $title = preg_replace('/\s*!!!\s*attention.*$/i', '', $title);

        return trim($title);
    }

    private function findEventId(string $title, string $date): ?int
    {
        // Exact match first
        $key = $title.'|'.$date;
        if (isset($this->eventTitleDateMap[$key])) {
            return $this->eventTitleDateMap[$key];
        }

        // Fuzzy: try matching by date and partial title
        $event = Event::where('event_date', $date)
            ->where(function ($q) use ($title) {
                $q->where('title', $title)
                    ->orWhere('title', 'LIKE', '%'.mb_substr($title, 0, 20).'%');
            })
            ->first();

        if ($event) {
            $this->eventTitleDateMap[$key] = $event->id;

            return $event->id;
        }

        return null;
    }

    // ─── 4. Payments ───────────────────────────────────────────

    private function importPayments(array $rows, bool $dryRun): void
    {
        $this->info('Importing payment history...');

        foreach ($rows as $row) {
            $email = trim($row['email'] ?? '');
            $userId = $this->findUserId($email);
            if (! $userId) {
                continue;
            }

            $paymentStr = trim($row['cb_paiement'] ?? '');
            $label = trim($row['cb_inscpaiement'] ?? '');
            $paidDateStr = trim($row['cb_datepaiement'] ?? '');

            if (! $paymentStr && ! $label) {
                continue;
            }

            // Parse amount
            $amount = $this->parseAmount($paymentStr);
            if ($amount <= 0 && ! $label) {
                continue;
            }

            // Extract season year from label
            $seasonYear = null;
            if (preg_match('/Cotisation\s+(\d{4})/i', $label, $m)) {
                $seasonYear = $m[1];
            }

            $paidDate = $this->parseLegacyDate($paidDateStr);

            // Avoid duplicates
            $exists = PaymentExpected::where('user_id', $userId)
                ->where('communication', $label ?: "Legacy import: {$paymentStr}")
                ->exists();
            if ($exists) {
                continue;
            }

            PaymentExpected::create([
                'user_id' => $userId,
                'type' => 'cotisation',
                'season_year' => $seasonYear,
                'amount_due' => $amount,
                'communication' => $label ?: "Legacy: {$paymentStr}",
                'status' => $paidDate ? 'paid' : ($amount > 0 ? 'pending' : 'paid'),
                'amount_paid' => $paidDate ? $amount : 0,
                'paid_at' => $paidDate,
            ]);
            $this->stats['payments']++;
        }

        $this->info("  Imported {$this->stats['payments']} payment records.");
    }

    private function parseAmount(string $val): float
    {
        $val = str_replace(['€', ' ', "\xc2\xa0"], '', $val);
        $val = str_replace(',', '.', $val);

        return (float) $val;
    }

    // ─── 5. Licences ───────────────────────────────────────────

    private function importLicences(array $rows, bool $dryRun): void
    {
        $this->info('Importing licence numbers...');

        // FFESSM federation id = 1
        $ffessmId = DB::table('federations')->where('acronym', 'FFESSM')->value('id') ?? 1;

        foreach ($rows as $row) {
            $email = trim($row['email'] ?? '');
            $userId = $this->findUserId($email);
            if (! $userId) {
                continue;
            }

            $licenceNo = trim($row['cb_nolicence'] ?? '');
            if (! $licenceNo) {
                continue;
            }

            // Check if already exists
            $exists = MemberLicence::where('user_id', $userId)
                ->where('licence_number', $licenceNo)
                ->exists();
            if ($exists) {
                continue;
            }

            // Also skip if user already has a licence for this federation
            $hasFed = MemberLicence::where('user_id', $userId)
                ->where('federation_id', $ffessmId)
                ->exists();
            if ($hasFed) {
                continue;
            }

            $requestDate = $this->parseLegacyDate($row['cb_dateenvoilicence'] ?? '');

            MemberLicence::create([
                'user_id' => $userId,
                'federation_id' => $ffessmId,
                'licence_number' => $licenceNo,
                'licence_request_date' => $requestDate,
                'licence_request_pending' => false,
            ]);
            $this->stats['licences']++;
        }

        $this->info("  Imported {$this->stats['licences']} licence records.");
    }

    // ─── Helpers ───────────────────────────────────────────────

    /**
     * Parse legacy date formats (DD/MM/YY, DD/MM/YYYY, DD/MM/YYYY HH:MM).
     * The old Joomla/CB system used European date format throughout.
     */
    private function parseLegacyDate(string $val): ?string
    {
        $val = trim($val);
        if (! $val || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') {
            return null;
        }

        // DD/MM/YY or DD/MM/YYYY
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', $val, $m)) {
            $y = $m[3];
            if (strlen($y) === 2) {
                $y = ((int) $y > 50) ? "19{$y}" : "20{$y}";
            }
            $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);

            return "{$y}-{$mo}-{$d}";
        }

        // DD/MM/YYYY HH:MM
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})\s#', $val, $m)) {
            $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);

            return "{$m[3]}-{$mo}-{$d}";
        }

        return null;
    }
}
