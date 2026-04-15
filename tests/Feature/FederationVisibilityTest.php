<?php

namespace Tests\Feature;

use App\Models\Federation;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group p1
 */
class FederationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Bureau Master', 'slug' => 'bureau_master']);
        \Spatie\Permission\Models\Role::findOrCreate('bureau_master', 'web');
        $status = MemberStatus::create(['name' => 'Actif', 'slug' => 'actif']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'status_id' => $status->id]);
        $this->admin->assignRole('bureau_master');
    }

    public function test_active_scope_filters_correctly(): void
    {
        Federation::create(['acronym' => 'FFESSM', 'full_name' => 'Test Active', 'visibility' => 'active']);
        Federation::create(['acronym' => 'HIDDEN', 'full_name' => 'Test Hidden', 'visibility' => 'invisible']);
        Federation::create(['acronym' => 'RECOG', 'full_name' => 'Test Recognized', 'visibility' => 'recognized']);

        $active = Federation::active()->pluck('acronym')->toArray();

        $this->assertContains('FFESSM', $active);
        $this->assertNotContains('HIDDEN', $active);
        $this->assertNotContains('RECOG', $active);
    }

    public function test_visible_scope_includes_active_and_recognized(): void
    {
        Federation::create(['acronym' => 'FFESSM', 'full_name' => 'Test Active', 'visibility' => 'active']);
        Federation::create(['acronym' => 'HIDDEN', 'full_name' => 'Test Hidden', 'visibility' => 'invisible']);
        Federation::create(['acronym' => 'RECOG', 'full_name' => 'Test Recognized', 'visibility' => 'recognized']);

        $visible = Federation::visible()->pluck('acronym')->toArray();

        $this->assertContains('FFESSM', $visible);
        $this->assertContains('RECOG', $visible);
        $this->assertNotContains('HIDDEN', $visible);
    }

    public function test_admin_can_update_federation_visibility(): void
    {
        $fed = Federation::create(['acronym' => 'TEST', 'full_name' => 'Test Fed', 'visibility' => 'active']);

        $response = $this->actingAs($this->admin)->put(route('admin.settings.federation.update', $fed), [
            'acronym' => 'TEST',
            'full_name' => 'Test Fed',
            'visibility' => 'invisible',
        ]);

        $response->assertRedirect();
        $this->assertEquals('invisible', $fed->fresh()->visibility);
    }

    public function test_new_federation_defaults_to_active(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.federation.store'), [
            'acronym' => 'NEW',
            'full_name' => 'New Federation',
        ]);

        $response->assertRedirect();
        $this->assertEquals('active', Federation::where('acronym', 'NEW')->first()->visibility);
    }
}
