<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberStatus;
use App\Models\StatusSet;
use App\Models\User;
use Database\Seeders\MemberStatusSeeder;
use Database\Seeders\StatusSetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class StatusSetSeederTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(MemberStatusSeeder::class);
    }

    public function test_seeder_creates_three_sets(): void
    {
        $this->seed(StatusSetSeeder::class);

        $this->assertNotNull(StatusSet::where('slug', 'fonctionnaire')->first());
        $this->assertNotNull(StatusSet::where('slug', 'externe')->first());
        $this->assertNotNull(StatusSet::where('slug', 'jeune')->first());
    }

    public function test_famille_associe_assimile_are_in_the_fonctionnaire_set(): void
    {
        $this->seed(StatusSetSeeder::class);

        $slugs = StatusSet::where('slug', 'fonctionnaire')->first()->statuses->pluck('slug')->all();
        $this->assertContains('famille', $slugs);
        $this->assertContains('associe', $slugs);
        $this->assertContains('assimile', $slugs);
        $this->assertContains('sympathisant', $slugs);
    }

    public function test_junior_and_enfant_are_in_the_jeune_set(): void
    {
        $this->seed(StatusSetSeeder::class);

        $slugs = StatusSet::where('slug', 'jeune')->first()->statuses->pluck('slug')->all();
        $this->assertContains('junior', $slugs);
        $this->assertContains('enfant', $slugs);
    }

    public function test_two_sympathisants_can_belong_to_different_sets_by_base_category(): void
    {
        // A base-fonctionnaire member and a base-externe member, both currently sympathisant.
        $fonctionnaireId = MemberStatus::where('slug', 'fonctionnaire')->value('id');
        $actifId = MemberStatus::where('slug', 'actif')->value('id');

        $fonc = User::factory()->create(['status_id' => $fonctionnaireId]);
        $ext = User::factory()->create(['status_id' => $actifId]);

        $this->seed(StatusSetSeeder::class);

        $fonc->refresh();
        $ext->refresh();

        $this->assertSame('fonctionnaire', $fonc->statusSet?->slug);
        $this->assertSame('externe', $ext->statusSet?->slug);

        // Now drop both to sympathisant; their sticky set stays, so they are
        // offered different upgrade paths.
        $sympId = MemberStatus::where('slug', 'sympathisant')->value('id');
        $fonc->update(['status_id' => $sympId]);
        $ext->update(['status_id' => $sympId]);

        $this->assertNotSame(
            $fonc->fresh()->statusSet?->slug,
            $ext->fresh()->statusSet?->slug
        );
    }

    public function test_default_status_is_marked_per_set(): void
    {
        $this->seed(StatusSetSeeder::class);

        $this->assertSame('fonctionnaire', StatusSet::where('slug', 'fonctionnaire')->first()->defaultStatus()?->slug);
        $this->assertSame('actif', StatusSet::where('slug', 'externe')->first()->defaultStatus()?->slug);
        $this->assertSame('junior', StatusSet::where('slug', 'jeune')->first()->defaultStatus()?->slug);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(StatusSetSeeder::class);
        $this->seed(StatusSetSeeder::class);

        $this->assertSame(1, StatusSet::where('slug', 'fonctionnaire')->count());
        $this->assertCount(
            7,
            StatusSet::where('slug', 'fonctionnaire')->first()->statuses
        );
    }
}
