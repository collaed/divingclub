<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CertificationLevel;
use App\Models\Federation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class BackfillCertificationStarsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private Federation $fed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->fed = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'FFESSM', 'visibility' => 'active']);
    }

    public function test_stars_the_highest_cert_for_a_member_with_no_star_yet(): void
    {
        $user = User::factory()->create();
        $n1 = CertificationLevel::create(['federation_id' => $this->fed->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);
        $n2 = CertificationLevel::create(['federation_id' => $this->fed->id, 'code' => 'N2', 'name' => 'Niveau 2', 'category' => 'diver', 'rank' => 40]);
        $user->certificationLevels()->attach([$n1->id => ['display_priority' => 0], $n2->id => ['display_priority' => 0]]);

        $this->artisan('certifications:backfill-stars')->assertSuccessful();

        $this->assertTrue((bool) $user->fresh()->certificationLevels()->find($n2->id)->pivot->is_primary);
        $this->assertFalse((bool) $user->fresh()->certificationLevels()->find($n1->id)->pivot->is_primary);
    }

    public function test_never_touches_a_member_who_already_has_a_star_even_if_its_not_the_highest(): void
    {
        $user = User::factory()->create();
        $n1 = CertificationLevel::create(['federation_id' => $this->fed->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);
        $n2 = CertificationLevel::create(['federation_id' => $this->fed->id, 'code' => 'N2', 'name' => 'Niveau 2', 'category' => 'diver', 'rank' => 40]);
        $user->certificationLevels()->attach([
            $n1->id => ['display_priority' => 0, 'is_primary' => true],
            $n2->id => ['display_priority' => 0, 'is_primary' => false],
        ]);

        $this->artisan('certifications:backfill-stars')->assertSuccessful();

        $this->assertTrue((bool) $user->fresh()->certificationLevels()->find($n1->id)->pivot->is_primary);
        $this->assertFalse((bool) $user->fresh()->certificationLevels()->find($n2->id)->pivot->is_primary);
    }
}
