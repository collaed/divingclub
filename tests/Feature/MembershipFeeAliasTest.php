<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MembershipFee;
use App\Models\MemberStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Membre de droit carries the base cotisation; Famille, Associé, Assimilé
 * and Fonctionnaire are sub-cases that pay the same unless the bureau sets a
 * fee of their own. Externe has its own rate and is not an alias.
 */
#[Group('p1')]
class MembershipFeeAliasTest extends TestCase
{
    use RefreshDatabase;

    private function memberStatus(string $slug): MemberStatus
    {
        return MemberStatus::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
    }

    private function fee(string $slug, string $year, float $amount): MembershipFee
    {
        return MembershipFee::create(['season_year' => $year, 'status_id' => $this->memberStatus($slug)->id, 'amount' => $amount, 'label' => $slug]);
    }

    /** @return array<string, array{string}> */
    public static function subCases(): array
    {
        return ['famille' => ['famille'], 'associé' => ['associe'], 'assimilé' => ['assimile'], 'fonctionnaire' => ['fonctionnaire']];
    }

    #[DataProvider('subCases')]
    public function test_a_sub_case_pays_the_membre_de_droit_fee(string $slug): void
    {
        $this->fee('membre_de_droit', '2027', 120);

        $fee = MembershipFee::resolveForStatus($this->memberStatus($slug), '2027');

        $this->assertSame('120.00', $fee?->amount);
    }

    public function test_a_sub_case_has_no_fee_when_membre_de_droit_has_none_for_that_season(): void
    {
        $this->fee('membre_de_droit', '2026', 105);

        $this->assertNull(MembershipFee::resolveForStatus($this->memberStatus('associe'), '2027'));
    }

    public function test_a_fee_set_directly_on_a_sub_case_wins_over_the_alias(): void
    {
        $this->fee('membre_de_droit', '2027', 120);
        $this->fee('fonctionnaire', '2027', 100);

        $this->assertSame('100.00', MembershipFee::resolveForStatus($this->memberStatus('fonctionnaire'), '2027')?->amount);
    }

    public function test_externe_is_not_an_alias_and_keeps_its_own_rate(): void
    {
        $this->fee('membre_de_droit', '2027', 120);
        $this->fee('externe', '2027', 130);

        $this->assertSame('130.00', MembershipFee::resolveForStatus($this->memberStatus('externe'), '2027')?->amount);
        $this->assertNull(MembershipFee::resolveForStatus($this->memberStatus('sympathisant'), '2027'));
    }
}
