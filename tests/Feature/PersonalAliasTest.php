<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailForwardingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * "sas.{name}@..." personal vanity aliases — see
 * MemberController::activatePersonalAlias() and the Email & Forwarding
 * settings page's "Personal Aliases" table.
 */
class PersonalAliasTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_bureau_master_can_activate_a_personal_alias(): void
    {
        $admin = $this->createBureauUser();
        $member = $this->createMemberUser();
        $member->detail->update(['first_name' => 'Eddy']);

        $this->actingAs($admin)
            ->post(route('admin.members.personal-alias.activate', $member))
            ->assertRedirect();

        $rule = MailForwardingRule::where('user_id', $member->id)->first();
        $this->assertNotNull($rule);
        $this->assertSame('sas.eddy@example.com', $rule->from_address);
        $this->assertStringContainsString($member->primary_email, $rule->to_addresses);
        $this->assertTrue($rule->enabled);
    }

    public function test_a_non_bureau_master_cannot_activate_a_personal_alias(): void
    {
        $finance = $this->createBureauUser();
        $finance->syncRoles(['bureau_finance']);
        $member = $this->createMemberUser();

        $this->actingAs($finance)
            ->post(route('admin.members.personal-alias.activate', $member))
            ->assertForbidden();

        $this->assertSame(0, MailForwardingRule::where('user_id', $member->id)->count());
    }

    public function test_activating_a_personal_alias_twice_is_a_noop(): void
    {
        $admin = $this->createBureauUser();
        $member = $this->createMemberUser();

        $this->actingAs($admin)->post(route('admin.members.personal-alias.activate', $member));
        $this->actingAs($admin)
            ->post(route('admin.members.personal-alias.activate', $member))
            ->assertSessionHas('error');

        $this->assertSame(1, MailForwardingRule::where('user_id', $member->id)->count());
    }
}
