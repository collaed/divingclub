<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LedgerCounterparty;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * A member's self-declared paying account (private-tab IBAN / account holder
 * name) must reach ledger_counterparties — the table LedgerClassificationService
 * actually matches against — so an import recognises their payments from the
 * very first statement line, not only after the fuzzy name heuristic first
 * succeeds. See ProfileController::syncLedgerCounterparty().
 */
#[Group('p1')]
class ProfileLedgerCounterpartySyncTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function member(): User
    {
        $status = MemberStatus::firstOrCreate(['slug' => 'active'], ['name' => 'Active']);
        $user = User::factory()->create(['status_id' => $status->id]);
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Marie', 'last_name' => 'Dupont']);
        $user->assignRole('member');

        return $user;
    }

    public function test_saving_an_iban_on_the_private_tab_creates_a_ledger_counterparty(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post(route('profile.update.private'), [
            'iban' => 'LU28 0019 4006 4475 0000',
        ])->assertSessionHasNoErrors();

        $counterparty = LedgerCounterparty::where('member_id', $user->id)->first();
        $this->assertNotNull($counterparty);
        $this->assertSame('LU280019400644750000', $counterparty->iban);
        $this->assertSame('Marie Dupont', $counterparty->name);
        $this->assertSame(LedgerCounterparty::KIND_MEMBER, $counterparty->kind);
    }

    public function test_saving_an_account_holder_name_creates_a_ledger_counterparty_without_an_iban(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post(route('profile.update.private'), [
            'account_holder_name' => 'Jean Dupont', // spouse's account
        ])->assertSessionHasNoErrors();

        $counterparty = LedgerCounterparty::where('member_id', $user->id)->first();
        $this->assertNotNull($counterparty);
        $this->assertSame('Jean Dupont', $counterparty->name);
        $this->assertNull($counterparty->iban);
    }

    public function test_saving_neither_field_does_not_create_a_counterparty(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post(route('profile.update.private'), [
            'city' => 'Luxembourg',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, LedgerCounterparty::where('member_id', $user->id)->count());
    }

    public function test_resaving_a_new_iban_updates_the_same_counterparty_rather_than_duplicating(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post(route('profile.update.private'), ['iban' => 'LU28 0019 4006 4475 0000']);
        $this->actingAs($user)->post(route('profile.update.private'), ['iban' => 'LU11 1111 1111 1111 1111']);

        $this->assertSame(1, LedgerCounterparty::where('member_id', $user->id)->count());
        $this->assertSame('LU111111111111111111', LedgerCounterparty::where('member_id', $user->id)->first()->iban);
    }
}
