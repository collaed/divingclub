<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MemberStatus;
use App\Models\StatusSet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seeds the named eligibility sets (base categories) and maps existing members
 * onto them based on their current status. The set is the sticky base category
 * (Fonctionnaire / Externe / Jeune); the member's current-year status is left
 * untouched. Idempotent — safe to re-run.
 */
class StatusSetSeeder extends Seeder
{
    /**
     * Set definitions: slug => [name, [statusSlug => isDefaultFull]].
     * `famille`, `associe`, `assimile` fold into the Fonctionnaire set.
     * `enfant`/`junior` form the new Jeune set.
     *
     * @var array<string, array{name: string, statuses: array<string, bool>}>
     */
    private const SETS = [
        'fonctionnaire' => [
            'name' => 'Fonctionnaire / Membre de droit',
            'statuses' => [
                'fonctionnaire' => true,
                'membre_de_droit' => false,
                'associe' => false,
                'assimile' => false,
                'famille' => false,
                'sympathisant' => false,
                'former' => false,
            ],
        ],
        'externe' => [
            'name' => 'Externe',
            'statuses' => [
                'actif' => true,
                'externe' => false,
                'sympathisant' => false,
                'former' => false,
            ],
        ],
        'jeune' => [
            'name' => 'Jeune',
            'statuses' => [
                'junior' => true,
                'enfant' => false,
                'former' => false,
            ],
        ],
    ];

    /**
     * Which set a member's current status maps to when back-filling existing
     * users. Statuses not listed (e.g. sympathisant, former, honoraire) are
     * ambiguous and left unmapped for the bureau to assign explicitly.
     *
     * @var array<string, string>
     */
    private const STATUS_TO_SET = [
        'fonctionnaire' => 'fonctionnaire',
        'membre_de_droit' => 'fonctionnaire',
        'associe' => 'fonctionnaire',
        'assimile' => 'fonctionnaire',
        'famille' => 'fonctionnaire',
        'actif' => 'externe',
        'externe' => 'externe',
        'junior' => 'jeune',
        'enfant' => 'jeune',
    ];

    public function run(): void
    {
        $statusIdBySlug = MemberStatus::pluck('id', 'slug');
        $setIdBySlug = [];

        foreach (self::SETS as $slug => $def) {
            $set = StatusSet::updateOrCreate(
                ['slug' => $slug],
                ['name' => $def['name']]
            );
            $setIdBySlug[$slug] = $set->id;

            $sync = [];
            foreach ($def['statuses'] as $statusSlug => $isDefault) {
                $statusId = $statusIdBySlug[$statusSlug] ?? null;
                if ($statusId === null) {
                    continue;
                }
                $sync[$statusId] = ['is_default' => $isDefault];
            }
            $set->statuses()->sync($sync);
        }

        $this->backfillUsers($statusIdBySlug, $setIdBySlug);
    }

    /**
     * @param  Collection<string, int>  $statusIdBySlug
     * @param  array<string, int>  $setIdBySlug
     */
    private function backfillUsers($statusIdBySlug, array $setIdBySlug): void
    {
        $setBySlugId = [];
        foreach (self::STATUS_TO_SET as $statusSlug => $setSlug) {
            $statusId = $statusIdBySlug[$statusSlug] ?? null;
            $setId = $setIdBySlug[$setSlug] ?? null;
            if ($statusId !== null && $setId !== null) {
                $setBySlugId[$statusId] = $setId;
            }
        }

        foreach ($setBySlugId as $statusId => $setId) {
            User::where('status_id', $statusId)
                ->whereNull('status_set_id')
                ->update(['status_set_id' => $setId]);
        }
    }
}
