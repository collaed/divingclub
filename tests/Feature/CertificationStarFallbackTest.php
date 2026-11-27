<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CertificationLevel;
use App\Models\Federation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * A member with only one cert in a track shouldn't need to click "Set" just
 * to show up under that level — primaryCertification()/
 * primaryFreedivingCertification() fall back to "the only one" when nobody
 * has starred anything yet.
 */
class CertificationStarFallbackTest extends TestCase
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

    public function test_falls_back_to_the_only_scuba_cert_when_none_is_starred(): void
    {
        $user = User::factory()->create();
        $n1 = CertificationLevel::create(['federation_id' => $this->fed->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);
        $user->certificationLevels()->attach($n1->id, ['display_priority' => 0]);

        $this->assertSame($n1->id, $user->primaryCertification()?->id);
    }

    public function test_does_not_fall_back_when_multiple_unstarred_scuba_certs_exist(): void
    {
        $user = User::factory()->create();
        $n1 = CertificationLevel::create(['federation_id' => $this->fed->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);
        $n2 = CertificationLevel::create(['federation_id' => $this->fed->id, 'code' => 'N2', 'name' => 'Niveau 2', 'category' => 'diver', 'rank' => 40]);
        $user->certificationLevels()->attach([$n1->id => ['display_priority' => 0], $n2->id => ['display_priority' => 0]]);

        $this->assertNull($user->primaryCertification());
    }

    public function test_an_explicit_star_always_wins_over_the_fallback(): void
    {
        $user = User::factory()->create();
        $n1 = CertificationLevel::create(['federation_id' => $this->fed->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);
        $user->certificationLevels()->attach($n1->id, ['display_priority' => 0, 'is_primary' => true]);

        $this->assertSame($n1->id, $user->primaryCertification()?->id);
    }

    public function test_falls_back_to_the_only_freediving_cert_when_none_is_starred(): void
    {
        $user = User::factory()->create();
        $atp = CertificationLevel::create(['federation_id' => $this->fed->id, 'code' => 'ATP', 'name' => 'Pass Apnéiste', 'category' => 'diver', 'discipline' => 'freediving', 'rank' => 4]);
        $user->certificationLevels()->attach($atp->id, ['display_priority' => 0]);

        $this->assertSame($atp->id, $user->primaryFreedivingCertification()?->id);
        // A member's single freediving cert has no bearing on the scuba star.
        $this->assertNull($user->primaryCertification());
    }

    public function test_a_specialty_only_member_has_no_scuba_fallback(): void
    {
        $user = User::factory()->create();
        $nitrox = CertificationLevel::create(['federation_id' => $this->fed->id, 'code' => 'NITROX', 'name' => 'Nitrox', 'category' => 'specialty', 'rank' => 200]);
        $user->certificationLevels()->attach($nitrox->id, ['display_priority' => 0]);

        $this->assertNull($user->primaryCertification());
    }
}
