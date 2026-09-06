<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FlassaState;
use App\Services\LicenceResolver;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p0')]
class LicenceResolverTest extends TestCase
{
    private LicenceResolver $resolver;

    private Carbon $anchor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new LicenceResolver;
        $this->anchor = Carbon::createFromDate(2026, 9, 1); // season 2027 anchor
    }

    private function dobForAge(int $age): Carbon
    {
        return $this->anchor->copy()->subYears($age);
    }

    public function test_sympathisant_gets_no_licence_no_flassa_no_assurance(): void
    {
        $d = $this->resolver->resolve('coti_sympathisant', $this->dobForAge(40), $this->anchor);

        $this->assertSame(LicenceResolver::FFESSM_NONE, $d->ffessmSlug);
        $this->assertSame(FlassaState::NotApplicable, $d->flassaState);
        $this->assertFalse($d->assuranceAllowed);
        $this->assertFalse($d->flassaState->isApplicable());
        $this->assertFalse($d->flassaState->appearsInCommunication());
    }

    public function test_sympathisant_by_status_slug_also_recognised(): void
    {
        $d = $this->resolver->resolve('sympathisant', $this->dobForAge(30), $this->anchor);
        $this->assertSame(LicenceResolver::FFESSM_NONE, $d->ffessmSlug);
        $this->assertSame(FlassaState::NotApplicable, $d->flassaState);
    }

    public function test_adult_fonctionnaire_gets_adulte_licence_and_flassa_required(): void
    {
        $d = $this->resolver->resolve('coti_fonctionnaire', $this->dobForAge(45), $this->anchor);

        $this->assertSame(LicenceResolver::FFESSM_ADULTE, $d->ffessmSlug);
        $this->assertSame(FlassaState::Required, $d->flassaState);
        $this->assertTrue($d->assuranceAllowed);
    }

    public function test_child_under_12_gets_enfant_licence_and_flassa_included_free(): void
    {
        $d = $this->resolver->resolve('coti_enfant', $this->dobForAge(9), $this->anchor);

        $this->assertSame(LicenceResolver::FFESSM_ENFANT, $d->ffessmSlug);
        $this->assertSame(FlassaState::IncludedFree, $d->flassaState);
        $this->assertTrue($d->assuranceAllowed);
    }

    public function test_jeune_12_to_under_16_gets_jeune_licence(): void
    {
        foreach ([12, 13, 14, 15] as $age) {
            $d = $this->resolver->resolve('coti_jeune_12_15', $this->dobForAge($age), $this->anchor);
            $this->assertSame(LicenceResolver::FFESSM_JEUNE, $d->ffessmSlug, "age {$age}");
            $this->assertSame(FlassaState::IncludedFree, $d->flassaState, "age {$age}");
        }
    }

    public function test_sixteen_and_seventeen_get_adulte_licence_but_flassa_included_free(): void
    {
        // R1-note: 16+ takes the adult FFESSM licence (full underwater
        // permissions) yet FLASSA stays included_free because still < 18.
        foreach ([16, 17] as $age) {
            $d = $this->resolver->resolve('coti_jeune_16_17', $this->dobForAge($age), $this->anchor);
            $this->assertSame(LicenceResolver::FFESSM_ADULTE, $d->ffessmSlug, "age {$age}");
            $this->assertSame(FlassaState::IncludedFree, $d->flassaState, "age {$age}");
        }
    }

    public function test_just_turned_16_on_jeune_band_still_gets_adulte_licence(): void
    {
        // R1-caveat: derivation is age-driven, so a 16yo on the club "12-15"
        // cotisation label correctly receives the adult licence, not jeune.
        $d = $this->resolver->resolve('coti_jeune_12_15', $this->dobForAge(16), $this->anchor);
        $this->assertSame(LicenceResolver::FFESSM_ADULTE, $d->ffessmSlug);
    }

    public function test_eighteen_flassa_becomes_required(): void
    {
        $d = $this->resolver->resolve('coti_externe', $this->dobForAge(18), $this->anchor);
        $this->assertSame(LicenceResolver::FFESSM_ADULTE, $d->ffessmSlug);
        $this->assertSame(FlassaState::Required, $d->flassaState);
    }

    public function test_null_dob_is_treated_as_adult(): void
    {
        $d = $this->resolver->resolve('coti_externe', null, $this->anchor);
        $this->assertSame(LicenceResolver::FFESSM_ADULTE, $d->ffessmSlug);
        $this->assertSame(FlassaState::Required, $d->flassaState);
    }
}
