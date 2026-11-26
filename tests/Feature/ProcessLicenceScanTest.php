<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessLicenceScan;
use App\Models\Federation;
use App\Models\LicenceScan;
use App\Models\MemberDetail;
use App\Models\MemberLicence;
use App\Models\User;
use App\Services\CloudflareVisionOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * A member's licence scan being successfully read and auto-applied is proof
 * the card has actually arrived — it should clear any outstanding
 * "licence_request_pending" flag left over from the renewal that requested
 * it, not just leave it however MembershipRenewalService last set it.
 */
class ProcessLicenceScanTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function scanFor(Federation $fed, int $uploaderId): LicenceScan
    {
        Storage::fake('local');
        $image = UploadedFile::fake()->image('card.jpg', 400, 300);
        Storage::disk('local')->put('licence-scans/uploads/card.jpg', file_get_contents($image->getPathname()));

        return LicenceScan::create([
            'federation_id' => $fed->id,
            'original_filename' => 'card.jpg',
            'file_path' => 'licence-scans/uploads/card.jpg',
            'status' => 'processing',
            'uploaded_by' => $uploaderId,
        ]);
    }

    public function test_a_successful_auto_apply_clears_a_pending_renewal_flag(): void
    {
        $fed = Federation::create(['acronym' => 'FLASSA', 'full_name' => 'FLASSA', 'visibility' => 'active']);
        $uploader = $this->createBureauUser();
        $member = User::factory()->create();
        MemberDetail::create(['user_id' => $member->id, 'first_name' => 'Michel', 'last_name' => 'Brochard']);
        $licence = MemberLicence::create([
            'user_id' => $member->id, 'federation_id' => $fed->id, 'season' => '2026', 'licence_request_pending' => true,
        ]);

        $scan = $this->scanFor($fed, $uploader->id);

        $ocr = Mockery::mock(CloudflareVisionOcrService::class);
        $ocr->shouldReceive('extractLicenceFields')->once()->andReturn(['name' => 'Michel Brochard', 'licence_number' => 'LS-0089', 'year' => '2027']);

        (new ProcessLicenceScan($scan->id))->handle($ocr);

        $scan->refresh();
        $this->assertSame('applied', $scan->status);
        $this->assertSame($member->id, $scan->matched_user_id);

        $this->assertFalse($licence->fresh()->licence_request_pending);
        $this->assertSame('LS-0089', $licence->fresh()->licence_number);
    }

    public function test_a_scan_that_cannot_be_matched_leaves_pending_untouched(): void
    {
        $fed = Federation::create(['acronym' => 'FLASSA', 'full_name' => 'FLASSA', 'visibility' => 'active']);
        $uploader = $this->createBureauUser();
        $member = User::factory()->create();
        MemberDetail::create(['user_id' => $member->id, 'first_name' => 'Michel', 'last_name' => 'Brochard']);
        $licence = MemberLicence::create([
            'user_id' => $member->id, 'federation_id' => $fed->id, 'season' => '2026', 'licence_request_pending' => true,
        ]);

        $scan = $this->scanFor($fed, $uploader->id);

        $ocr = Mockery::mock(CloudflareVisionOcrService::class);
        $ocr->shouldReceive('extractLicenceFields')->once()->andReturn(['name' => 'Nobody Here', 'licence_number' => null, 'year' => null]);

        (new ProcessLicenceScan($scan->id))->handle($ocr);

        $this->assertSame('needs_review', $scan->fresh()->status);
        $this->assertTrue($licence->fresh()->licence_request_pending);
    }
}
