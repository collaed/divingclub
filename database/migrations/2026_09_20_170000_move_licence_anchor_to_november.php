<?php

declare(strict_types=1);

use App\Models\MembershipFeeComponent;
use Illuminate\Database\Migrations\Migration;

/**
 * Licences are ordered from November, so ages (licence band, FLASSA, the
 * under-18 cotisation share) are measured on 1 November of the anchor's year
 * instead of 1 September.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->moveAnchors('09-01', '11-01');
    }

    public function down(): void
    {
        $this->moveAnchors('11-01', '09-01');
    }

    private function moveAnchors(string $from, string $to): void
    {
        MembershipFeeComponent::whereNotNull('age_anchor_date')->get()
            ->filter(fn (MembershipFeeComponent $c): bool => $c->age_anchor_date->format('m-d') === $from)
            ->each(fn (MembershipFeeComponent $c) => $c->update(['age_anchor_date' => $c->age_anchor_date->year.'-'.$to]));
    }
};
