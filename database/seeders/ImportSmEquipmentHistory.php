<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class ImportSmEquipmentHistory extends Seeder
{
    public function run(): void
    {
        $response = Http::withHeaders(['X-Sync-Key' => 'cep-sync-2026-hetzner'])
            ->timeout(60)
            ->get('https://clubcep.eu/wrapp/api_sm.php');

        if (! $response->ok()) {
            $this->command->error('API returned '.$response->status());

            return;
        }

        $data = $response->json();
        $this->command->info("Fetched: {$data['counts']['equipment']} equipment, {$data['counts']['movements']} movements, {$data['counts']['borrowers']} borrowers");

        // 1. Import equipment items
        $equipMap = []; // old id => new Equipment
        foreach ($data['equipment'] as $item) {
            $name = trim($item['nom']);
            if (! $name) {
                continue;
            }

            $type = $this->guessType($name);
            $eq = Equipment::firstOrCreate(
                ['name' => $name],
                [
                    'type' => $type,
                    'condition' => 'good',
                    'status' => 'available',
                    'is_loanable' => true,
                ]
            );
            $equipMap[$item['id']] = $eq;
        }
        $this->command->info(count($equipMap).' equipment items mapped.');

        // 2. Map borrowers to users
        $userMap = []; // old dest id => User
        foreach ($data['borrowers'] as $b) {
            $email = $b['user_email'] ?? null;
            $destName = $b['dest_name'] ?? '';
            $userName = $b['user_name'] ?? '';

            // Try email match first
            if ($email) {
                $user = User::where('primary_email', $email)->first();
                if ($user) {
                    $userMap[$b['id']] = $user;

                    continue;
                }
            }

            // Try name match
            $name = $userName ?: $destName;
            if ($name) {
                $parts = preg_split('/\s+/', trim($name), 2);
                if (count($parts) === 2) {
                    $user = User::whereHas('detail', fn ($q) => $q->where('first_name', 'ILIKE', $parts[0])
                        ->where('last_name', 'ILIKE', $parts[1])
                    )->first();
                    if (! $user) {
                        // Try reversed
                        $user = User::whereHas('detail', fn ($q) => $q->where('first_name', 'ILIKE', $parts[1])
                            ->where('last_name', 'ILIKE', $parts[0])
                        )->first();
                    }
                    if ($user) {
                        $userMap[$b['id']] = $user;
                    }
                }
            }
        }
        $this->command->info(count($userMap).' borrowers matched to users.');

        // 3. Import movements as equipment loans
        $imported = 0;
        $skipped = 0;
        foreach ($data['movements'] as $m) {
            $eq = $equipMap[$m['id_matos']] ?? null;
            $user = $userMap[$m['id_dest']] ?? null;

            if (! $eq || ! $user) {
                $skipped++;

                continue;
            }

            $loanedAt = $m['horo_sortie'] ? date('Y-m-d', strtotime($m['horo_sortie'])) : null;
            $returnedAt = $m['horo_retour'] ? date('Y-m-d', strtotime($m['horo_retour'])) : null;

            if (! $loanedAt && ! $returnedAt) {
                $skipped++;

                continue;
            }

            // Skip if already imported (same equipment + user + date)
            $exists = EquipmentLoan::where('equipment_id', $eq->id)
                ->where('user_id', $user->id)
                ->where('loaned_at', $loanedAt ?: $returnedAt)
                ->exists();

            if ($exists) {
                continue;
            }

            EquipmentLoan::create([
                'equipment_id' => $eq->id,
                'user_id' => $user->id,
                'loaned_at' => $loanedAt ?: $returnedAt,
                'returned_at' => $returnedAt,
                'loan_reason' => $m['rem'] ?: ($m['sortie_titre'] ? 'SM: '.$m['sortie_titre'] : null),
            ]);

            // Update equipment status if still out
            if (! $returnedAt) {
                $eq->update(['status' => 'on_loan']);
            }

            $imported++;
        }

        $this->command->info("Imported: {$imported} loans, skipped: {$skipped} (no match or empty).");

        // Unmatched borrowers for review
        $unmatched = collect($data['borrowers'])->filter(fn ($b) => ! isset($userMap[$b['id']]))->pluck('dest_name')->filter()->unique()->values();
        if ($unmatched->isNotEmpty()) {
            $this->command->warn('Unmatched borrowers: '.$unmatched->implode(', '));
        }
    }

    private function guessType(string $name): string
    {
        $n = mb_strtolower($name);
        if (preg_match('/stab|bcd|gilet/i', $n)) {
            return 'bcd';
        }
        if (preg_match('/detend|reg|octo/i', $n)) {
            return 'regulator';
        }
        if (preg_match('/bloc|tank|bout/i', $n)) {
            return 'tank';
        }
        if (preg_match('/combi|suit|wet/i', $n)) {
            return 'wetsuit';
        }
        if (preg_match('/masque|mask/i', $n)) {
            return 'mask';
        }
        if (preg_match('/palm|fin/i', $n)) {
            return 'fins';
        }
        if (preg_match('/ordi|computer/i', $n)) {
            return 'computer';
        }
        if (preg_match('/lamp|phare|torch/i', $n)) {
            return 'other';
        }

        return 'other';
    }
}
