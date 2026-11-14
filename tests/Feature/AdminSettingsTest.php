<?php

namespace Tests\Feature;

use App\Models\Federation;
use App\Models\MailForwardingRule;
use App\Models\ThemeSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->createBureauUser();
    }

    public function test_guest_cannot_access_settings(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect(route('login'));
    }

    public function test_bureau_can_view_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.index'))
            ->assertOk();
    }

    public function test_settings_page_omits_license_details_when_no_key_is_configured(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('Licensed for up to');
    }

    public function test_settings_page_omits_license_details_for_a_tampered_key(): void
    {
        $payload = base64_encode(json_encode(['domain' => 'test.com', 'max_members' => 500, 'expires' => '2030-01-01']));
        ThemeSetting::set('license_key', $payload.'.'.base64_encode('fake-signature'));

        $this->actingAs($this->admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('Licensed for up to');
    }

    public function test_bureau_can_update_theme_colors(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.theme.update'), ['primary_color' => '#ff0000'])
            ->assertRedirect();

        $this->assertEquals('#ff0000', ThemeSetting::get('primary_color'));
    }

    public function test_bureau_can_switch_site_layout(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.theme.update'), ['site_layout' => 'professional'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('professional', ThemeSetting::get('site_layout'));
    }

    public function test_invalid_layout_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.theme.update'), ['site_layout' => 'hacked'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull(ThemeSetting::get('site_layout'));
    }

    public function test_bureau_can_apply_color_preset(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.theme.preset'), ['preset' => 'coral'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('#c0392b', ThemeSetting::get('primary_color'));
    }

    public function test_bureau_can_crud_federation(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.federations.store'), [
                'acronym' => 'TEST',
                'full_name' => 'Test Federation',
                'visibility' => 'active',
            ])
            ->assertRedirect();

        $fed = Federation::where('acronym', 'TEST')->first();
        $this->assertNotNull($fed);

        $this->actingAs($this->admin)
            ->put(route('admin.federations.bulk-update'), [
                'fed' => [
                    $fed->id => ['acronym' => 'TST', 'full_name' => 'Test Fed Renamed', 'visibility' => 'recognized'],
                ],
            ])
            ->assertRedirect();

        $this->assertEquals('TST', $fed->fresh()->acronym);
        $this->assertEquals('recognized', $fed->fresh()->visibility);

        $this->actingAs($this->admin)
            ->delete(route('admin.federations.destroy', $fed))
            ->assertRedirect();

        $this->assertNull(Federation::find($fed->id));
    }

    public function test_bureau_can_update_mail_settings(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.theme.update'), [
                'mail_from_address' => 'noreply@clubcep.eu',
                'mail_from_name' => 'CEP',
                'mail_reply_to' => 'reply@clubcep.eu',
                'mail_inbound_address' => 'inbound@clubcep.eu',
            ])
            ->assertRedirect();

        $this->assertSame('noreply@clubcep.eu', ThemeSetting::get('mail_from_address'));
        $this->assertSame('CEP', ThemeSetting::get('mail_from_name'));
        $this->assertSame('reply@clubcep.eu', ThemeSetting::get('mail_reply_to'));
        $this->assertSame('inbound@clubcep.eu', ThemeSetting::get('mail_inbound_address'));
    }

    public function test_guest_cannot_manage_forwarding_rules(): void
    {
        $this->post(route('admin.settings.forwarding-rule.store'), [
            'from_address' => 'info@example.com', 'to_addresses' => 'club@gmail.example',
        ])->assertRedirect(route('login'));
    }

    public function test_bureau_can_crud_a_forwarding_rule(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.forwarding-rule.store'), [
                'from_address' => 'info@example.com', 'to_addresses' => 'club@gmail.example', 'label' => 'Public contact',
            ])
            ->assertRedirect();

        $rule = MailForwardingRule::where('from_address', 'info@example.com')->first();
        $this->assertNotNull($rule);
        $this->assertTrue($rule->enabled);

        $this->actingAs($this->admin)
            ->put(route('admin.settings.forwarding-rule.update', $rule), [
                'to_addresses' => 'other@gmail.example', 'label' => 'Renamed', 'enabled' => false,
            ])
            ->assertRedirect();

        $rule->refresh();
        $this->assertSame('other@gmail.example', $rule->to_addresses);
        $this->assertFalse($rule->enabled);

        $this->actingAs($this->admin)
            ->delete(route('admin.settings.forwarding-rule.destroy', $rule))
            ->assertRedirect();

        $this->assertNull(MailForwardingRule::find($rule->id));
    }

    public function test_forwarding_rule_from_address_must_be_unique(): void
    {
        MailForwardingRule::create(['from_address' => 'info@example.com', 'to_addresses' => 'a@example.com']);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.forwarding-rule.store'), [
                'from_address' => 'info@example.com', 'to_addresses' => 'b@example.com',
            ])
            ->assertSessionHasErrors('from_address');

        $this->assertSame(1, MailForwardingRule::where('from_address', 'info@example.com')->count());
    }
}
