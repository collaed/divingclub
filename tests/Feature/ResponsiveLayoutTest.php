<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class ResponsiveLayoutTest extends TestCase
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

    public function test_x_table_wraps_in_responsive_container(): void
    {
        $html = Blade::render('<x-table id="t1" class="table-hover"><thead><tr><th>A</th></tr></thead></x-table>');

        $this->assertStringContainsString('table-responsive', $html);
        $this->assertMatchesRegularExpression('/<table[^>]*\bid="t1"/', $html);
        $this->assertMatchesRegularExpression('/<table[^>]*\bclass="[^"]*\btable\b[^"]*\btable-hover\b/', $html);
        $this->assertStringNotContainsString('table-stack', $html);
    }

    public function test_x_table_stack_adds_the_stack_class(): void
    {
        $html = Blade::render('<x-table stack><tbody><tr><td data-label="X">1</td></tr></tbody></x-table>');

        $this->assertMatchesRegularExpression('/<table[^>]*\bclass="[^"]*\btable-stack\b/', $html);
    }

    public function test_members_directory_uses_the_table_component_with_data_labels(): void
    {
        $bureau = User::factory()->create();
        $bureau->assignRole('bureau_master');
        MemberDetail::factory()->create(['user_id' => $bureau->id]);

        $m = User::factory()->create(['status_id' => 1]);
        $m->assignRole('member');
        MemberDetail::factory()->create(['user_id' => $m->id, 'first_name' => 'Jean', 'last_name' => 'Dupont']);
        MemberStatus::firstOrCreate(['id' => 1], ['name' => 'Active', 'slug' => 'active']);

        $res = $this->actingAs($bureau)->get('/members');
        $res->assertOk();
        $res->assertSee('table-responsive', false);
        $res->assertSee('table-stack', false);
        $res->assertSee('data-label="Name"', false);
    }
}
