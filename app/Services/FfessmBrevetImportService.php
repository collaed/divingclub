<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CertificationLevel;
use App\Models\Federation;
use App\Models\MemberLicence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Imports the FFESSM "Liste des brevets" club export: one row per
 * certification (brevet) granted to a member, with its date, level, and the
 * member's FFESSM licence number. Updates each matched member's
 * certification history (user_certification_levels) and federation licence
 * number (MemberLicence) — never creates a new member, and never guesses a
 * name match: anything that doesn't resolve to exactly one existing member
 * is left out and reported back, the same convention ProcessLicenceScan
 * uses for licence-card OCR.
 *
 * The export's layout (blank rows, a "Saison YYYY/YYYY" banner, then a
 * header row whose exact position varies by export) is fixed by FFESSM's
 * own tool; we locate the header by content rather than assuming a row
 * number, but the column order below it is relied on exactly.
 */
class FfessmBrevetImportService
{
    /** Exact "Niveau" label (as FFESSM writes it) => our CertificationLevel code. */
    private const LEVEL_MAP = [
        'Plongeur Bronze' => 'Bronze',
        'Plongeur Argent' => 'Argent',
        'Plongeur Or' => 'Or',
        'Pass Apnéiste (ATP)' => 'ATP',
        'Pass Apnéiste' => 'ATP',
        'Apnéiste / Indoor Freediver 1 CMAS' => 'APNEA_1',
        'Niveau 1' => 'N1',
        'Niveau 2' => 'N2',
        'Niveau 3' => 'N3',
        'Plongeur encadré 40 mètres' => 'PE40',
        'Plongeur Nitrox' => 'NITROX',
        'Plongeur Nitrox confirmé' => 'NITROX_C',
        'RIFA Plongée' => 'RIFAP',
        'RIFA Apnée' => 'RIFA_APNEE',
        'Combinaison étanche' => 'DRYSUIT',
    ];

    /**
     * @return array{
     *     season: string|null, rows: int, certs_created: int, certs_updated: int, licences_updated: int,
     *     unmatched_names: array<int, string>, ambiguous_names: array<int, string>, unmatched_levels: array<int, string>,
     * }
     */
    public function import(string $path, string $originalName): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        $headerRow = null;
        foreach ($rows as $i => $row) {
            if (trim((string) ($row[0] ?? '')) === 'Date Obtention') {
                $headerRow = $i;
                break;
            }
        }
        if ($headerRow === null) {
            throw new \RuntimeException('Unrecognised brevets export: no "Date Obtention" header row found.');
        }

        $season = $this->extractSeason($rows);
        $federationId = Federation::where('acronym', 'FFESSM')->value('id');
        if (! $federationId) {
            throw new \RuntimeException('The FFESSM federation is not configured. Seed CertificationLevelSeeder first.');
        }
        $levelsByCode = CertificationLevel::where('federation_id', $federationId)->get()->keyBy('code');
        $userIndex = $this->buildUserIndex();

        $certsCreated = 0;
        $certsUpdated = 0;
        $licencesUpdated = 0;
        $unmatchedNames = [];
        $ambiguousNames = [];
        $unmatchedLevels = [];
        $processedRows = 0;
        $touchedUserIds = [];

        foreach (array_slice($rows, $headerRow + 1) as $row) {
            $dateRaw = trim((string) ($row[0] ?? ''));
            if ($dateRaw === '') {
                continue; // blank separator row
            }
            $processedRows++;

            $niveau = trim((string) ($row[3] ?? ''));
            $code = self::LEVEL_MAP[$niveau] ?? null;
            if ($code === null) {
                $unmatchedLevels[$niveau] = true;

                continue;
            }
            $level = $levelsByCode->get($code);
            if ($level === null) {
                $unmatchedLevels[$niveau] = true;

                continue;
            }

            $date = $this->asDate($dateRaw);
            $licenceNumber = trim((string) ($row[8] ?? ''));
            $rawName = trim((string) ($row[10] ?? ''));

            $key = $this->normalizeKey($rawName);
            $candidates = $userIndex[$key] ?? [];
            if (count($candidates) === 0) {
                $unmatchedNames[$rawName] = true;

                continue;
            }
            if (count($candidates) > 1) {
                $ambiguousNames[$rawName] = true;

                continue;
            }
            /** @var User $user */
            $user = $candidates[0];

            if ($date !== null) {
                $hasPivot = $user->certificationLevels()->where('certification_level_id', $level->id)->exists();
                if ($hasPivot) {
                    $user->certificationLevels()->updateExistingPivot($level->id, ['obtained_date' => $date]);
                    $certsUpdated++;
                } else {
                    $user->certificationLevels()->attach($level->id, [
                        'obtained_date' => $date, 'is_primary' => false, 'display_priority' => 0,
                    ]);
                    $certsCreated++;
                }
                $touchedUserIds[$user->id] = true;
            }

            if ($licenceNumber !== '') {
                MemberLicence::updateOrCreate(
                    ['user_id' => $user->id, 'federation_id' => $federationId],
                    ['licence_number' => $licenceNumber, 'season' => $season]
                );
                $licencesUpdated++;
            }
        }

        // Re-star each touched member's highest scuba and freediving certs —
        // only for members this import actually changed, never anyone else's
        // star left over from a manual choice.
        foreach (array_keys($touchedUserIds) as $userId) {
            User::find($userId)?->recomputeCertificationStars();
        }

        $result = [
            'season' => $season,
            'rows' => $processedRows,
            'certs_created' => $certsCreated,
            'certs_updated' => $certsUpdated,
            'licences_updated' => $licencesUpdated,
            'unmatched_names' => array_keys($unmatchedNames),
            'ambiguous_names' => array_keys($ambiguousNames),
            'unmatched_levels' => array_keys($unmatchedLevels),
        ];
        Log::info('FFESSM brevets import finished', ['file' => $originalName] + $result);

        return $result;
    }

    private function asDate(string $value): ?Carbon
    {
        try {
            return Carbon::createFromFormat('d/m/Y', $value)?->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param  array<int, array<int, mixed>>  $rows */
    private function extractSeason(array $rows): ?string
    {
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if (is_string($cell) && preg_match('/Saison\s+(\d{4})\/(\d{4})/u', $cell, $m)) {
                    return $m[1].'-'.$m[2];
                }
            }
        }

        return null;
    }

    /**
     * Every member keyed by their order-independent normalized name, so a
     * row's "LASTNAME Firstname" (or any other word order — the export's
     * own ordering isn't assumed) matches regardless of how it's stored.
     *
     * @return array<string, array<int, User>>
     */
    private function buildUserIndex(): array
    {
        $index = [];
        foreach (User::whereHas('detail')->with('detail')->get() as $user) {
            $key = $this->normalizeKey(trim(($user->detail->first_name ?? '').' '.($user->detail->last_name ?? '')));
            if ($key === '') {
                continue;
            }
            $index[$key][] = $user;
        }

        return $index;
    }

    private function normalizeKey(string $name): string
    {
        // A hyphenated double surname on file (e.g. "PUENTE-CASTAN") is
        // space-separated in the FFESSM export ("PUENTE CASTAN ...") —
        // split on both so the two forms produce the same token set.
        $ascii = str_replace('-', ' ', Str::of($name)->ascii()->upper()->toString());
        $tokens = array_values(array_filter(explode(' ', Str::of($ascii)->squish()->toString())));
        sort($tokens);

        return implode(' ', $tokens);
    }
}
