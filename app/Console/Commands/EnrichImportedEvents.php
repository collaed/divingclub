<?php

declare(strict_types=1);

/**
 * Post-import enrichment of calendar events imported from Google Calendar.
 *
 * Fills in missing metadata (colors, responsible person, max participants,
 * levels display) based on event type and title pattern matching. Run after
 * import:legacy to bring imported events up to the new system's standards.
 *
 * @author  ClubCEP.eu
 *
 * @see     \App\Console\Commands\ImportLegacyData  — the import this enriches
 */

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnrichImportedEvents extends Command
{
    protected $signature = 'import:enrich-events {--dry-run : Preview without writing}';

    protected $description = 'Enrich imported calendar events with colors, responsible, max_participants based on type patterns';

    // Color mapping — matches the calendar color scheme used in the old system
    private array $typeColors = [
        'pool' => '#0077be',
        'dive' => '#003366',
        'training' => '#28a745',
        'theory' => '#6f42c1',
        'social' => '#ffc107',
        'trip' => '#e83e8c',
        'other' => '#6c757d',
    ];

    // Default max participants by type
    private array $typeMaxParticipants = [
        'pool' => 30,
        'dive' => 20,
        'training' => 12,
        'theory' => 30,
        'social' => null,
        'trip' => null,
        'other' => null,
    ];

    // Responsible person by title pattern — each instructor has their regular activities
    // (e.g., apnée always led by Pietro, pool sessions at Steinfort by Michel)
    private array $responsiblePatterns = [
        '/apn[ée]e/i' => 91,       // Pietro Giancola — apnée lead
        '/steinfort/i' => 61,       // Michel Brochard
        '/merl|ecolage/i' => 61,    // Michel Brochard
        '/fosse|coque/i' => 66,     // Keran Chaussard
        '/nemo/i' => 66,            // Keran Chaussard
        '/lac|sûre/i' => 92,        // Vincent Girard
        '/z[ée]lande/i' => 92,      // Vincent Girard
        '/th[ée]or|cours|examen/i' => 130, // Gilles Saleten
        '/nitrox/i' => 130,         // Gilles Saleten
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $stats = ['color' => 0, 'responsible' => 0, 'max_participants' => 0, 'levels_display' => 0];

        $events = Event::whereNull('color_hex')
            ->orWhereNull('responsible_id')
            ->orWhereNull('max_participants')
            ->get();

        $this->info("Processing {$events->count()} events...");

        DB::beginTransaction();

        foreach ($events as $event) {
            $updates = [];

            // Color
            if (! $event->color_hex && isset($this->typeColors[$event->event_type])) {
                $updates['color_hex'] = $this->typeColors[$event->event_type];
                $stats['color']++;
            }

            // Responsible
            if (! $event->responsible_id) {
                $respId = $this->matchResponsible($event->title);
                if ($respId) {
                    $updates['responsible_id'] = $respId;
                    $stats['responsible']++;
                }
            }

            // Max participants
            if (! $event->max_participants && isset($this->typeMaxParticipants[$event->event_type])) {
                $max = $this->typeMaxParticipants[$event->event_type];
                // Apnée sessions are smaller
                if (preg_match('/apn[ée]e/i', $event->title)) {
                    $max = 12;
                }
                if ($max) {
                    $updates['max_participants'] = $max;
                    $stats['max_participants']++;
                }
            }

            // Levels display — enable for pool/dive events that had inscriptions
            if (! $event->levels_display && in_array($event->event_type, ['pool', 'dive', 'training'])) {
                $updates['levels_display'] = true;
                $stats['levels_display']++;
            }

            if ($updates) {
                $event->update($updates);
            }
        }

        if ($dryRun) {
            DB::rollBack();
            $this->warn('DRY RUN — no changes written.');
        } else {
            DB::commit();
        }

        $this->info('=== Enrichment Summary ===');
        foreach ($stats as $key => $val) {
            $this->line("  {$key}: {$val}");
        }

        return 0;
    }

    private function matchResponsible(string $title): ?int
    {
        foreach ($this->responsiblePatterns as $pattern => $userId) {
            if (preg_match($pattern, $title)) {
                return $userId;
            }
        }

        return null;
    }
}
