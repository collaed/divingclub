<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CertificationLevel;
use App\Models\DivingPrerogative;
use App\Models\Federation;
use Illuminate\Database\Seeder;

class DivingPrerogativeSeeder extends Seeder
{
    public function run(): void
    {
        // Create prerogatives
        $prerogatives = [
            ['code' => 'PE-12', 'name' => 'Plongeur Encadré 12m', 'type' => 'supervised', 'max_depth' => 12, 'requires_adult' => false],
            ['code' => 'PE-20', 'name' => 'Plongeur Encadré 20m', 'type' => 'supervised', 'max_depth' => 20, 'requires_adult' => false],
            ['code' => 'PE-40', 'name' => 'Plongeur Encadré 40m', 'type' => 'supervised', 'max_depth' => 40, 'requires_adult' => false],
            ['code' => 'PE-60', 'name' => 'Plongeur Encadré 60m', 'type' => 'supervised', 'max_depth' => 60, 'requires_adult' => false],
            ['code' => 'PA-12', 'name' => 'Plongeur Autonome 12m', 'type' => 'autonomous', 'max_depth' => 12, 'requires_adult' => true],
            ['code' => 'PA-20', 'name' => 'Plongeur Autonome 20m', 'type' => 'autonomous', 'max_depth' => 20, 'requires_adult' => true],
            ['code' => 'PA-40', 'name' => 'Plongeur Autonome 40m', 'type' => 'autonomous', 'max_depth' => 40, 'requires_adult' => true],
            ['code' => 'PA-60', 'name' => 'Plongeur Autonome 60m', 'type' => 'autonomous', 'max_depth' => 60, 'requires_adult' => true],
            ['code' => 'GP', 'name' => 'Guide de Palanquée', 'type' => 'guide', 'max_depth' => 40, 'requires_adult' => true],
            ['code' => 'GP-60', 'name' => 'Guide de Palanquée 60m', 'type' => 'guide', 'max_depth' => 60, 'requires_adult' => true],
            ['code' => 'E1', 'name' => 'Enseignement Initiateur', 'type' => 'teach', 'max_depth' => 6, 'requires_adult' => true],
            ['code' => 'E2', 'name' => 'Enseignement N4+Init (PE-20, guide 40m)', 'type' => 'teach', 'max_depth' => 40, 'requires_adult' => true],
            ['code' => 'E3', 'name' => 'Enseignement MF1 (PE-40, guide 40m)', 'type' => 'teach', 'max_depth' => 40, 'requires_adult' => true],
            ['code' => 'E4', 'name' => 'Enseignement MF2 (PE-60, guide 60m)', 'type' => 'teach', 'max_depth' => 60, 'requires_adult' => true],
            ['code' => 'DP', 'name' => 'Directeur de Plongée', 'type' => 'guide', 'max_depth' => 60, 'requires_adult' => true],
        ];

        foreach ($prerogatives as $p) {
            DivingPrerogative::updateOrCreate(['code' => $p['code']], $p);
        }

        // Map FFESSM certifications → prerogatives
        $mapping = [
            // Diver levels
            'PE20' => ['PE-12', 'PE-20'],
            'N1' => ['PE-12', 'PE-20'],
            'PE40' => ['PE-12', 'PE-20', 'PE-40'],
            'PA20' => ['PE-12', 'PE-20', 'PA-12', 'PA-20'],
            'N2' => ['PE-12', 'PE-20', 'PE-40', 'PA-12', 'PA-20'],
            'PA40' => ['PE-12', 'PE-20', 'PE-40', 'PA-12', 'PA-20', 'PA-40'],
            'N3' => ['PE-12', 'PE-20', 'PE-40', 'PE-60', 'PA-12', 'PA-20', 'PA-40', 'PA-60'],
            'N4' => ['PE-12', 'PE-20', 'PE-40', 'PE-60', 'PA-12', 'PA-20', 'PA-40', 'PA-60', 'GP'],
            'N5' => ['PE-12', 'PE-20', 'PE-40', 'PE-60', 'PA-12', 'PA-20', 'PA-40', 'PA-60', 'GP', 'DP'],
            // Instructor levels
            'E1' => ['PE-12', 'PE-20', 'PE-40', 'PE-60', 'PA-12', 'PA-20', 'PA-40', 'PA-60', 'GP', 'E1'],
            'E2' => ['PE-12', 'PE-20', 'PE-40', 'PE-60', 'PA-12', 'PA-20', 'PA-40', 'PA-60', 'GP', 'E1', 'E2'],
            'E3' => ['PE-12', 'PE-20', 'PE-40', 'PE-60', 'PA-12', 'PA-20', 'PA-40', 'PA-60', 'GP', 'GP-60', 'E1', 'E2', 'E3'],
            'E4' => ['PE-12', 'PE-20', 'PE-40', 'PE-60', 'PA-12', 'PA-20', 'PA-40', 'PA-60', 'GP', 'GP-60', 'E1', 'E2', 'E3', 'E4'],
            'E5' => ['PE-12', 'PE-20', 'PE-40', 'PE-60', 'PA-12', 'PA-20', 'PA-40', 'PA-60', 'GP', 'GP-60', 'DP', 'E1', 'E2', 'E3', 'E4'],
        ];

        $ffessm = Federation::where('acronym', 'FFESSM')->first();
        if (! $ffessm) {
            return;
        }

        $prerogIds = DivingPrerogative::pluck('id', 'code');

        foreach ($mapping as $certCode => $prerogCodes) {
            $cert = CertificationLevel::where('federation_id', $ffessm->id)->where('code', $certCode)->first();
            if (! $cert) {
                continue;
            }
            $ids = collect($prerogCodes)->map(fn ($code) => $prerogIds[$code] ?? null)->filter()->all();
            $cert->prerogatives()->syncWithoutDetaching($ids);
        }

        echo 'Seeded '.DivingPrerogative::count()." prerogatives, mapped to FFESSM certifications.\n";
    }
}
