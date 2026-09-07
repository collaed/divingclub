<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fold legacy / free-text nationality spellings onto the canonical ISO short
 * names in config/countries.php so member statistics stop counting the same
 * country twice (e.g. "Czech Republic" and "Czechia"). Data-only, idempotent:
 * a second run matches no rows. Portable across MySQL + PostgreSQL + SQLite
 * (plain where/update, comparison done in PHP).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_details') || ! Schema::hasColumn('member_details', 'nationality')) {
            return;
        }

        /** @var list<string> $canonical */
        $canonical = array_values((array) config('countries.all', []));

        foreach ($this->aliasMap() as $legacy => $target) {
            if (! in_array($target, $canonical, true)) {
                // Never introduce a value the picker / stats don't recognise.
                continue;
            }

            DB::table('member_details')
                ->where('nationality', $legacy)
                ->update(['nationality' => $target]);
        }

        // Collapse accidental surrounding whitespace ("France " -> "France").
        $values = DB::table('member_details')
            ->whereNotNull('nationality')
            ->distinct()
            ->pluck('nationality');

        foreach ($values as $value) {
            $trimmed = trim((string) $value);

            if ($trimmed !== '' && $trimmed !== (string) $value) {
                DB::table('member_details')
                    ->where('nationality', $value)
                    ->update(['nationality' => $trimmed]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible: the original free-text spelling of each row is not recoverable.
    }

    /**
     * Legacy spelling => canonical name (must exist in config('countries.all')).
     *
     * @return array<string, string>
     */
    private function aliasMap(): array
    {
        return [
            'UK' => 'United Kingdom',
            'U.K.' => 'United Kingdom',
            'Great Britain' => 'United Kingdom',
            'England' => 'United Kingdom',
            'Scotland' => 'United Kingdom',
            'Wales' => 'United Kingdom',
            'Northern Ireland' => 'United Kingdom',

            'USA' => 'United States',
            'U.S.A.' => 'United States',
            'US' => 'United States',
            'U.S.' => 'United States',
            'America' => 'United States',
            'United States of America' => 'United States',

            'Czech Republic' => 'Czechia',
            'Bosnia' => 'Bosnia and Herzegovina',
            'Bosnia-Herzegovina' => 'Bosnia and Herzegovina',
            'Bosnia Herzegovina' => 'Bosnia and Herzegovina',

            'Holland' => 'Netherlands',
            'The Netherlands' => 'Netherlands',
            'Russian Federation' => 'Russia',
            'Republic of Ireland' => 'Ireland',
            'Eire' => 'Ireland',
            'Macedonia' => 'North Macedonia',
            'Swaziland' => 'Eswatini',
            'Cabo Verde' => 'Cape Verde',
            "Cote d'Ivoire" => 'Ivory Coast',
            "Côte d'Ivoire" => 'Ivory Coast',
            'Burma' => 'Myanmar',
            'East Timor' => 'Timor-Leste',
            'UAE' => 'United Arab Emirates',
            'South Korea (Republic of Korea)' => 'South Korea',
            'Korea, South' => 'South Korea',
            'Korea, North' => 'North Korea',
        ];
    }
};
