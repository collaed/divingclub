<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Season;
use App\Models\ThemeSetting;
use App\Models\User;
use Database\Seeders\Fee2027Seeder;
use Database\Seeders\MemberStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class DuesBankSettingsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(MemberStatusSeeder::class);
        Season::factory()->create(['year' => '2027', 'start_date' => '2026-09-01', 'fee_taper_tiers' => null]);
        $this->seed(Fee2027Seeder::class);
    }

    private function member(): User
    {
        $status = MemberStatus::where('slug', 'externe')->firstOrFail();
        $user = User::factory()->create(['status_id' => $status->id, 'email_verified_at' => now()]);
        MemberDetail::create([
            'user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Member',
            'date_of_birth' => Carbon::createFromDate(1990, 1, 1)->toDateString(),
        ]);

        return $user->fresh(['detail', 'status']);
    }

    public function test_payment_block_shows_bank_details_when_iban_set(): void
    {
        ThemeSetting::set('club_iban', 'LU21 0019 7855 8919 6000');
        ThemeSetting::set('club_bic', 'BCEELULL');
        ThemeSetting::set('club_full_name', 'CLUB EUROPEEN DE PLONGEE');

        $user = $this->member();
        $this->actingAs($user)->post(route('dues.calculate'), [
            'season_year' => '2027', 'status_id' => $user->status_id,
            'last_name' => 'Member', 'first_name' => 'Test',
        ])->assertOk()
            ->assertSee('LU21 0019 7855 8919 6000')
            ->assertSee('CLUB EUROPEEN DE PLONGEE');
    }

    public function test_payment_block_shows_notice_when_iban_missing(): void
    {
        ThemeSetting::where('key', 'club_iban')->delete();

        $user = $this->member();
        $this->actingAs($user)->post(route('dues.calculate'), [
            'season_year' => '2027', 'status_id' => $user->status_id,
            'last_name' => 'Member', 'first_name' => 'Test',
        ])->assertOk()
            ->assertSee(__('Club IBAN not configured. Ask an administrator to set it in Settings → Banking.'));
    }

    public function test_bureau_can_save_grace_days_setting(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('bureau_master');

        $this->actingAs($admin)->post(route('admin.settings.theme.update'), [
            'dues_cutoff_grace_days' => '14',
        ])->assertRedirect();

        $this->assertSame('14', ThemeSetting::get('dues_cutoff_grace_days'));
    }
}
