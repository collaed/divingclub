<?php

namespace Tests\Feature;

use App\Jobs\SendEquipmentReminders;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

#[Group('p1')]
class SendEquipmentRemindersOrphanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member']], ['id']);
        SpatieRole::findOrCreate('member', 'web');
        MemberStatus::upsert([['id' => 1, 'name' => 'Active', 'slug' => 'active']], ['id']);
    }

    public function test_overdue_reminder_skips_loans_whose_equipment_was_retired(): void
    {
        $borrower = $this->member();

        $liveLoan = $this->overdueLoan($borrower, Equipment::factory()->create());

        // A loan whose equipment has since been soft-deleted (retired gear):
        // $loan->equipment resolves to null and used to crash the job.
        $retired = Equipment::factory()->create();
        $orphanLoan = $this->overdueLoan($borrower, $retired);
        $retired->delete();

        (new SendEquipmentReminders)->handle();

        $this->assertNotNull($liveLoan->fresh()->reminder_sent_at, 'valid loan should be reminded');
        $this->assertNull($orphanLoan->fresh()->reminder_sent_at, 'orphaned loan should be skipped, not processed');
    }

    public function test_overdue_reminder_skips_loans_whose_borrower_was_removed(): void
    {
        $liveLoan = $this->overdueLoan($this->member(), Equipment::factory()->create());

        $leaver = $this->member();
        $orphanLoan = $this->overdueLoan($leaver, Equipment::factory()->create());
        $leaver->delete(); // User uses SoftDeletes

        (new SendEquipmentReminders)->handle();

        $this->assertNotNull($liveLoan->fresh()->reminder_sent_at);
        $this->assertNull($orphanLoan->fresh()->reminder_sent_at);
    }

    private function member(): User
    {
        $u = User::create([
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'P',
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'M', 'last_name' => 'E']);

        return $u;
    }

    private function overdueLoan(User $user, Equipment $equipment): EquipmentLoan
    {
        return EquipmentLoan::create([
            'equipment_id' => $equipment->id,
            'user_id' => $user->id,
            'loaned_at' => now()->subDays(60),
            'expected_return_date' => now()->subDays(10),
            'returned_at' => null,
            'reminder_sent_at' => null,
        ]);
    }
}
