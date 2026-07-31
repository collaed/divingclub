<?php

namespace Tests\Feature;

use App\Models\Federation;
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
            ->post(route('admin.settings.federation.store'), [
                'acronym' => 'TEST',
                'full_name' => 'Test Federation',
                'visibility' => 'active',
            ])
            ->assertRedirect();

        $fed = Federation::where('acronym', 'TEST')->first();
        $this->assertNotNull($fed);

        $this->actingAs($this->admin)
            ->put(route('admin.settings.federation.update', $fed), [
                'acronym' => 'TST',
                'full_name' => 'Test Fed Renamed',
                'visibility' => 'recognized',
            ])
            ->assertRedirect();

        $this->assertEquals('TST', $fed->fresh()->acronym);

        $this->actingAs($this->admin)
            ->delete(route('admin.settings.federation.destroy', $fed))
            ->assertRedirect();

        $this->assertNull(Federation::find($fed->id));
    }
}
