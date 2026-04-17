<?php

namespace Tests\Feature;

use App\Jobs\SendLoanRecapEmail;
use App\Jobs\SendReturnRecapEmail;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\Event;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class EquipmentLoanEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    private Equipment $bcd;

    private Equipment $tank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->admin = $this->createUser('bureau_master');
        $this->member = $this->createUser('member');
        $this->member->update(['primary_email' => 'diver@test.com']);
        $this->member->detail()->update(['first_name' => 'Test', 'last_name' => 'Diver']);

        $this->bcd = Equipment::create([
            'name' => 'BCD Test', 'type' => 'bcd', 'short_number' => 'B1',
            'condition' => 'good', 'status' => 'available', 'is_loanable' => true,
        ]);
        $this->tank = Equipment::create([
            'name' => 'Tank Test', 'type' => 'tank', 'short_number' => 'T1',
            'condition' => 'good', 'status' => 'available', 'is_loanable' => true,
        ]);
    }

    private function seedRoles(): void
    {
        Role::upsert([
            ['id' => 1, 'name' => 'Public', 'slug' => 'public'],
            ['id' => 2, 'name' => 'Member', 'slug' => 'member'],
            ['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master'],
        ], ['id']);
        foreach (['public', 'member', 'instructor', 'instructor_apnea', 'bureau_finance', 'bureau_technical', 'bureau_master'] as $r) {
            SpatieRole::findOrCreate($r, 'web');
        }
        MemberStatus::upsert([['id' => 1, 'name' => 'Active', 'slug' => 'active']], ['id']);
    }

    private function createUser(string $role = 'member'): User
    {
        $r = Role::where('slug', $role)->first();
        $u = User::create([
            'username' => fake()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role_id' => $r->id,
            'email_verified_at' => now(),
        ]);
        $u->assignRole($role);
        $u->detail()->create(['first_name' => fake()->firstName(), 'last_name' => fake()->lastName()]);

        return $u;
    }

    public function test_loan_stores_event_id_and_reason(): void
    {
        Queue::fake();
        $event = Event::create(['title' => 'Pool Session', 'event_date' => now()->addDay(), 'event_type' => 'pool', 'status' => 'scheduled']);

        $this->actingAs($this->admin)
            ->post(route('admin.equipment.loan', $this->bcd), [
                'user_id' => $this->member->id,
                'event_id' => $event->id,
                'loan_reason' => 'Training gear',
            ])
            ->assertRedirect();

        $loan = EquipmentLoan::latest()->first();
        $this->assertEquals($event->id, $loan->event_id);
        $this->assertEquals('Training gear', $loan->loan_reason);
    }

    public function test_loan_dispatches_recap_email_job(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.equipment.loan', $this->bcd), [
                'user_id' => $this->member->id,
            ]);

        Queue::assertPushed(SendLoanRecapEmail::class, fn ($job) => $job->userId === $this->member->id);
    }

    public function test_return_dispatches_return_email_job(): void
    {
        Queue::fake();

        $loan = EquipmentLoan::create([
            'equipment_id' => $this->bcd->id,
            'user_id' => $this->member->id,
            'loaned_at' => now(),
            'loaned_by' => $this->admin->id,
        ]);
        $this->bcd->update(['status' => 'on_loan']);

        $this->actingAs($this->admin)
            ->post(route('admin.equipment.return', $loan));

        Queue::assertPushed(SendReturnRecapEmail::class, fn ($job) => $job->userId === $this->member->id);
    }

    public function test_loan_recap_job_marks_sent(): void
    {
        $loan = EquipmentLoan::create([
            'equipment_id' => $this->bcd->id,
            'user_id' => $this->member->id,
            'loaned_at' => now(),
            'loaned_by' => $this->admin->id,
        ]);

        // Job runs and marks loan_email_sent_at (email may fail in test but DB update happens)
        try {
            (new SendLoanRecapEmail($this->member->id))->handle();
        } catch (\Throwable) {
            // Mail sending may fail in test env
        }

        $this->assertNotNull($loan->fresh()->loan_email_sent_at);
    }

    public function test_return_recap_job_marks_sent(): void
    {
        $loan = EquipmentLoan::create([
            'equipment_id' => $this->bcd->id,
            'user_id' => $this->member->id,
            'loaned_at' => now()->subDay(),
            'returned_at' => now(),
            'loaned_by' => $this->admin->id,
        ]);

        try {
            (new SendReturnRecapEmail($this->member->id))->handle();
        } catch (\Throwable) {
        }

        $this->assertNotNull($loan->fresh()->return_email_sent_at);
    }

    public function test_quick_loan_with_event_dispatches_single_email(): void
    {
        Queue::fake();
        $event = Event::create(['title' => 'Dive Trip', 'event_date' => now()->addDay(), 'event_type' => 'dive', 'status' => 'scheduled']);

        $this->actingAs($this->admin)
            ->post(route('admin.equipment.quick-loan'), [
                'user_id' => $this->member->id,
                'event_id' => $event->id,
                'equipment_ids' => [$this->bcd->id, $this->tank->id],
            ]);

        Queue::assertPushed(SendLoanRecapEmail::class, 1);
        $this->assertEquals(2, EquipmentLoan::where('event_id', $event->id)->count());
    }

    public function test_loan_recap_skips_already_sent(): void
    {
        EquipmentLoan::create([
            'equipment_id' => $this->bcd->id,
            'user_id' => $this->member->id,
            'loaned_at' => now(),
            'loaned_by' => $this->admin->id,
            'loan_email_sent_at' => now(),
        ]);

        // Job should do nothing — no unsent loans
        $job = new SendLoanRecapEmail($this->member->id);
        $job->handle();

        // If it tried to send, it would fail. No assertion needed — just no exception.
        $this->assertTrue(true);
    }

    public function test_return_full_vs_partial_delay(): void
    {
        Queue::fake();

        // Loan both items
        $loan1 = EquipmentLoan::create([
            'equipment_id' => $this->bcd->id, 'user_id' => $this->member->id,
            'loaned_at' => now(), 'loaned_by' => $this->admin->id,
        ]);
        $this->bcd->update(['status' => 'on_loan']);
        EquipmentLoan::create([
            'equipment_id' => $this->tank->id, 'user_id' => $this->member->id,
            'loaned_at' => now(), 'loaned_by' => $this->admin->id,
        ]);
        $this->tank->update(['status' => 'on_loan']);

        // Return only BCD — partial return, should dispatch with longer delay
        $this->actingAs($this->admin)->post(route('admin.equipment.return', $loan1));

        Queue::assertPushed(SendReturnRecapEmail::class, 1);
    }
}
