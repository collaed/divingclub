<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberStatus;
use App\Services\MailAliasCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Each checkbox is one AND'd predicate — see Admin\MailAliasController's
 * builder and MailAliasService::resolveDefinition().
 */
class MailAliasCriteriaTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_current_member_matches_active_and_excludes_unpaid(): void
    {
        $paidUp = $this->createMemberUser();
        $paidUp->detail->update(['cotisation_years' => [(string) now()->year]]);
        $unpaid = $this->createMemberUser();

        $matched = MailAliasCriteria::matchingUsers(['current_member'])->pluck('id');

        $this->assertTrue($matched->contains($paidUp->id));
        $this->assertFalse($matched->contains($unpaid->id));
    }

    public function test_sympathisant_matches_only_that_status(): void
    {
        $sympathisant = MemberStatus::create(['name' => 'Sympathisant', 'slug' => 'sympathisant']);
        $member = $this->createMemberUser();
        $member->update(['status_id' => $sympathisant->id]);
        $other = $this->createMemberUser();

        $matched = MailAliasCriteria::matchingUsers(['sympathisant'])->pluck('id');

        $this->assertTrue($matched->contains($member->id));
        $this->assertFalse($matched->contains($other->id));
    }

    public function test_de_droit_matches_famille_and_fonctionnaire_but_not_externe(): void
    {
        $famille = MemberStatus::create(['name' => 'Famille', 'slug' => 'famille']);
        $externe = MemberStatus::create(['name' => 'Externe', 'slug' => 'externe']);

        $familyMember = $this->createMemberUser();
        $familyMember->update(['status_id' => $famille->id]);
        $externalMember = $this->createMemberUser();
        $externalMember->update(['status_id' => $externe->id]);

        $matched = MailAliasCriteria::matchingUsers(['de_droit'])->pluck('id');

        $this->assertTrue($matched->contains($familyMember->id));
        $this->assertFalse($matched->contains($externalMember->id));
    }

    public function test_late_payer_matches_last_season_but_not_current(): void
    {
        $lastYear = (string) (((int) now()->year) - 1);

        $latePayer = $this->createMemberUser();
        $latePayer->detail->update(['cotisation_years' => [$lastYear]]);
        $currentPayer = $this->createMemberUser();
        $currentPayer->detail->update(['cotisation_years' => [(string) now()->year]]);
        $neverPaid = $this->createMemberUser();

        $matched = MailAliasCriteria::matchingUsers(['late_payer'])->pluck('id');

        $this->assertTrue($matched->contains($latePayer->id));
        $this->assertFalse($matched->contains($currentPayer->id));
        $this->assertFalse($matched->contains($neverPaid->id));
    }

    public function test_bureau_criterion_matches_bureau_member_flag(): void
    {
        $bureauMember = $this->createMemberUser();
        $bureauMember->detail->update(['bureau_member' => true]);
        $other = $this->createMemberUser();

        $matched = MailAliasCriteria::matchingUsers(['bureau'])->pluck('id');

        $this->assertTrue($matched->contains($bureauMember->id));
        $this->assertFalse($matched->contains($other->id));
    }

    public function test_instructor_criterion_matches_active_instructor_flag(): void
    {
        $instructor = $this->createMemberUser();
        $instructor->detail->update(['active_instructor' => true]);
        $other = $this->createMemberUser();

        $matched = MailAliasCriteria::matchingUsers(['instructor'])->pluck('id');

        $this->assertTrue($matched->contains($instructor->id));
        $this->assertFalse($matched->contains($other->id));
    }

    public function test_lifeguard_criterion_matches_is_lifeguard_flag(): void
    {
        $lifeguard = $this->createMemberUser();
        $lifeguard->detail->update(['is_lifeguard' => true]);
        $other = $this->createMemberUser();

        $matched = MailAliasCriteria::matchingUsers(['lifeguard'])->pluck('id');

        $this->assertTrue($matched->contains($lifeguard->id));
        $this->assertFalse($matched->contains($other->id));
    }

    public function test_training_level_criteria_are_independent_checkboxes(): void
    {
        $n1 = $this->createMemberUser();
        $n1->detail->update(['training_enrollments' => ['N1']]);
        $n2 = $this->createMemberUser();
        $n2->detail->update(['training_enrollments' => ['N2']]);

        $matchedN1 = MailAliasCriteria::matchingUsers(['pn1'])->pluck('id');
        $matchedN2 = MailAliasCriteria::matchingUsers(['pn2'])->pluck('id');

        $this->assertTrue($matchedN1->contains($n1->id));
        $this->assertFalse($matchedN1->contains($n2->id));
        $this->assertTrue($matchedN2->contains($n2->id));
        $this->assertFalse($matchedN2->contains($n1->id));
    }

    public function test_training_flag_criteria_match_rifap_nitrox_qualifs(): void
    {
        $rifap = $this->createMemberUser();
        $rifap->detail->update(['training_enrollments' => ['RIFAP']]);
        $nitrox = $this->createMemberUser();
        $nitrox->detail->update(['training_enrollments' => ['NITROX']]);

        $matchedRifap = MailAliasCriteria::matchingUsers(['rifap'])->pluck('id');
        $matchedNitrox = MailAliasCriteria::matchingUsers(['nitrox'])->pluck('id');

        $this->assertTrue($matchedRifap->contains($rifap->id));
        $this->assertFalse($matchedRifap->contains($nitrox->id));
        $this->assertTrue($matchedNitrox->contains($nitrox->id));
    }

    public function test_selected_criteria_are_anded_together_not_ored(): void
    {
        $bureauAndInstructor = $this->createMemberUser();
        $bureauAndInstructor->detail->update(['bureau_member' => true, 'active_instructor' => true]);
        $bureauOnly = $this->createMemberUser();
        $bureauOnly->detail->update(['bureau_member' => true]);
        $instructorOnly = $this->createMemberUser();
        $instructorOnly->detail->update(['active_instructor' => true]);

        $matched = MailAliasCriteria::matchingUsers(['bureau', 'instructor'])->pluck('id');

        $this->assertTrue($matched->contains($bureauAndInstructor->id));
        $this->assertFalse($matched->contains($bureauOnly->id));
        $this->assertFalse($matched->contains($instructorOnly->id));
    }

    /**
     * An unknown key contributes no predicate rather than erroring — but,
     * unlike a real criterion, it also doesn't fall back to "match everyone":
     * zero real criteria and zero named includes means zero matches, which is
     * what lets an all-unticked selection safely mean "named members only"
     * instead of accidentally spamming the whole club.
     */
    public function test_unknown_keys_are_ignored_and_contribute_no_match(): void
    {
        $this->createMemberUser();

        $this->assertSame(0, MailAliasCriteria::matchingUsers(['not_a_real_key'])->count());
    }

    public function test_an_included_member_is_added_even_without_matching_any_criterion(): void
    {
        $bureauMember = $this->createMemberUser();
        $bureauMember->detail->update(['bureau_member' => true]);
        $outsider = $this->createMemberUser();

        $matched = MailAliasCriteria::matchingUsers(['bureau'], [$outsider->id])->pluck('id');

        $this->assertTrue($matched->contains($bureauMember->id));
        $this->assertTrue($matched->contains($outsider->id));
    }

    public function test_an_excluded_member_is_removed_even_if_they_match_every_criterion(): void
    {
        $bureauMemberA = $this->createMemberUser();
        $bureauMemberA->detail->update(['bureau_member' => true]);
        $bureauMemberB = $this->createMemberUser();
        $bureauMemberB->detail->update(['bureau_member' => true]);

        $matched = MailAliasCriteria::matchingUsers(['bureau'], [], [$bureauMemberB->id])->pluck('id');

        $this->assertTrue($matched->contains($bureauMemberA->id));
        $this->assertFalse($matched->contains($bureauMemberB->id));
    }

    public function test_a_group_can_be_built_from_named_members_only_with_no_criteria(): void
    {
        $named = $this->createMemberUser();
        $other = $this->createMemberUser();

        $matched = MailAliasCriteria::matchingUsers([], [$named->id])->pluck('id');

        $this->assertSame([$named->id], $matched->all());
        $this->assertFalse($matched->contains($other->id));
    }

    public function test_exclude_wins_over_include_for_the_same_member(): void
    {
        $member = $this->createMemberUser();

        $matched = MailAliasCriteria::matchingUsers([], [$member->id], [$member->id])->pluck('id');

        $this->assertFalse($matched->contains($member->id));
    }
}
