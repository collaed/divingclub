<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\Federation;
use App\Models\MedicalComplianceRule;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\MedicalComplianceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

#[Group('p0')]
class MedicalComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    private MedicalComplianceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MedicalComplianceService;
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member']], ['id']);
        SpatieRole::findOrCreate('member', 'web');
        MemberStatus::upsert([['id' => 1, 'name' => 'Active', 'slug' => 'active']], ['id']);
    }

    private function createMember(string $dob = '1985-03-15'): User
    {
        $u = User::create(['primary_email' => fake()->unique()->safeEmail(), 'password' => 'P', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'T', 'last_name' => 'U', 'date_of_birth' => $dob]);

        return $u;
    }

    private function createFederation(string $acronym, int $minAge, int $maxAge, int $months): Federation
    {
        $fed = Federation::create(['acronym' => $acronym, 'full_name' => $acronym, 'visibility' => 'active']);
        MedicalComplianceRule::create(['federation_id' => $fed->id, 'age_bracket_low' => $minAge, 'age_bracket_high' => $maxAge, 'validity_months' => $months, 'cert_type' => 'general']);

        return $fed;
    }

    private function doc(User $user, array $attrs = []): Document
    {
        return Document::create(array_merge([
            'user_id' => $user->id, 'category' => 'medical', 'is_current' => true,
            'file_path' => 'x', 'original_filename' => 'x', 'mime_type' => 'application/pdf', 'size_bytes' => 1,
        ], $attrs));
    }

    public function test_user_without_cert_is_not_compliant(): void
    {
        $this->assertFalse($this->service->isCompliant($this->createMember()));
    }

    public function test_user_with_valid_cert_is_compliant(): void
    {
        $user = $this->createMember();
        $this->doc($user, ['expiry_date' => now()->addMonths(6)]);
        $this->assertTrue($this->service->isCompliant($user));
    }

    public function test_user_with_expired_cert_is_not_compliant(): void
    {
        $user = $this->createMember();
        $this->doc($user, ['expiry_date' => now()->subDay()]);
        $this->assertFalse($this->service->isCompliant($user));
    }

    public function test_status_returns_missing_when_no_cert(): void
    {
        $status = $this->service->getStatus($this->createMember());
        $this->assertEquals('missing', $status['status']);
        $this->assertEquals('danger', $status['badge']);
    }

    public function test_status_returns_expiring_within_30_days(): void
    {
        $user = $this->createMember();
        $this->doc($user, ['expiry_date' => now()->addDays(15)]);
        $status = $this->service->getStatus($user);
        $this->assertEquals('expiring', $status['status']);
        $this->assertEquals('warning', $status['badge']);
    }

    public function test_evaluate_sets_expiry_date(): void
    {
        $user = $this->createMember();
        $this->createFederation('TESTFED', 0, 99, 12);

        $doc = $this->doc($user, ['date_established' => Carbon::parse('2026-01-15')]);
        $this->service->evaluateCertificate($doc);
        $doc->refresh();

        $this->assertNotNull($doc->expiry_date);
        $this->assertNotEmpty($doc->compliance_notes);
        $this->assertTrue($doc->expiry_date->isAfter(Carbon::parse('2026-01-15')));
    }

    public function test_evaluate_supersedes_previous_cert(): void
    {
        $user = $this->createMember();
        $this->createFederation('FFESSM', 0, 99, 12);

        $old = $this->doc($user, ['file_path' => 'old', 'original_filename' => 'old', 'date_established' => Carbon::parse('2025-01-01'), 'expiry_date' => Carbon::parse('2026-01-01')]);
        $new = $this->doc($user, ['file_path' => 'new', 'original_filename' => 'new', 'date_established' => Carbon::parse('2026-03-01')]);

        $this->service->evaluateCertificate($new);
        $old->refresh();

        $this->assertFalse((bool) $old->is_current);
        $this->assertEquals($new->id, $old->superseded_by);
    }

    public function test_compliance_checked_at_future_date(): void
    {
        $user = $this->createMember();
        $this->doc($user, ['expiry_date' => now()->addMonths(3)]);

        $this->assertTrue($this->service->isCompliant($user, now()->addMonths(2)));
        $this->assertFalse($this->service->isCompliant($user, now()->addMonths(4)));
    }

    public function test_status_compliant_has_days_remaining(): void
    {
        $user = $this->createMember();
        $this->doc($user, ['expiry_date' => now()->addDays(90)]);
        $status = $this->service->getStatus($user);
        $this->assertEquals('compliant', $status['status']);
        $this->assertGreaterThan(80, $status['days']);
    }

    public function test_status_expired_cert(): void
    {
        $user = $this->createMember();
        $this->doc($user, ['expiry_date' => now()->subDays(10)]);
        $status = $this->service->getStatus($user);
        $this->assertEquals('expired', $status['status']);
        $this->assertEquals('danger', $status['badge']);
    }
}
