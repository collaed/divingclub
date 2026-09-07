<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use App\Services\MemberExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class MemberExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table($roleTable)->insertOrIgnore(['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
        SpatieRole::findOrCreate('bureau_master', 'web');
    }

    public function test_bureau_can_open_the_export_page(): void
    {
        $this->actingAs($this->member('bureau_master'))
            ->get('/admin/members/export')
            ->assertOk()
            ->assertSee('Member Data Export');
    }

    public function test_bureau_can_download_the_xlsx(): void
    {
        $this->member();

        $response = $this->actingAs($this->member('bureau_master'))
            ->get('/admin/members/export/download');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $response->headers->get('content-type'));
    }

    public function test_regular_member_cannot_access_the_export(): void
    {
        $this->actingAs($this->member('member'))
            ->get('/admin/members/export')
            ->assertForbidden();
    }

    public function test_service_produces_one_denormalized_row_per_member(): void
    {
        $this->member();
        $this->member();

        $data = app(MemberExportService::class)->build();

        $this->assertContains('Email', $data['headers']);
        $this->assertContains('Nationality', $data['headers']);
        $this->assertCount(2, $data['rows']);
        $this->assertCount(count($data['headers']), $data['rows'][0]);
    }

    private function member(string $role = 'member'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        MemberDetail::factory()->create(['user_id' => $user->id]);

        return $user;
    }
}
