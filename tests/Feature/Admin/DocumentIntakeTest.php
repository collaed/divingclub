<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Jobs\ClassifyDocumentIntake;
use App\Jobs\ProcessLicenceScan;
use App\Models\DocumentIntake;
use App\Models\Federation;
use App\Services\BankReconciliationService;
use App\Services\DocumentClassifierService;
use App\Services\PdfTextExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class DocumentIntakeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_plain_member_is_forbidden(): void
    {
        $this->actingAs($this->createMemberUser())
            ->get(route('admin.document-intake.index'))
            ->assertForbidden();
    }

    public function test_upload_queues_one_intake_per_file_and_dispatches_classification(): void
    {
        Bus::fake();

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.document-intake.store'), [
                'files' => [
                    UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, DocumentIntake::count());
        $this->assertSame(0, DocumentIntake::where('status', '!=', 'processing')->count());
        Bus::assertDispatchedTimes(ClassifyDocumentIntake::class, 2);
    }

    public function test_reclassify_as_licence_scan_requires_a_federation(): void
    {
        $intake = DocumentIntake::create(['original_filename' => 'a.pdf', 'file_path' => 'x', 'status' => 'needs_review', 'uploaded_by' => $this->createBureauUser()->id]);

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.document-intake.reclassify', $intake), ['type' => 'licence_scan'])
            ->assertSessionHasErrors('federation_id');
    }

    public function test_reclassify_dispatches_the_job_with_the_forced_type_and_resets_status(): void
    {
        Bus::fake();
        $fed = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'FFESSM', 'visibility' => 'active']);
        $intake = DocumentIntake::create(['original_filename' => 'a.pdf', 'file_path' => 'x', 'status' => 'needs_review', 'error' => 'Could not tell.', 'uploaded_by' => $this->createBureauUser()->id]);

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.document-intake.reclassify', $intake), ['type' => 'licence_scan', 'federation_id' => $fed->id])
            ->assertRedirect();

        $intake->refresh();
        $this->assertSame('processing', $intake->status);
        $this->assertNull($intake->error);
        Bus::assertDispatched(ClassifyDocumentIntake::class, fn (ClassifyDocumentIntake $job): bool => $job->intakeId === $intake->id && $job->forcedType === 'licence_scan' && $job->forcedFederationId === $fed->id
        );
    }

    public function test_job_routes_a_manually_forced_licence_scan_without_running_the_classifier(): void
    {
        Bus::fake([ProcessLicenceScan::class]);
        Storage::fake('local');
        $fed = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'FFESSM', 'visibility' => 'active']);
        Storage::disk('local')->put('document-intake/uploads/a.pdf', '%PDF fake content');
        $intake = DocumentIntake::create(['original_filename' => 'a.pdf', 'file_path' => 'document-intake/uploads/a.pdf', 'status' => 'processing', 'uploaded_by' => $this->createBureauUser()->id]);

        (new ClassifyDocumentIntake($intake->id, 'licence_scan', $fed->id))->handle(
            app(PdfTextExtractionService::class),
            app(DocumentClassifierService::class),
            app(BankReconciliationService::class),
        );

        $intake->refresh();
        $this->assertSame('routed', $intake->status);
        $this->assertSame('licence_scan', $intake->detected_type);
        $this->assertNotNull($intake->licence_scan_id);
        $this->assertDatabaseHas('licence_scans', ['id' => $intake->licence_scan_id, 'federation_id' => $fed->id]);
    }

    public function test_job_routes_a_manually_forced_bank_statement_using_the_extracted_text(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('document-intake/uploads/b.pdf', '%PDF fake content');
        $intake = DocumentIntake::create(['original_filename' => 'b.pdf', 'file_path' => 'document-intake/uploads/b.pdf', 'status' => 'processing']);

        $fakeExtractor = new class extends PdfTextExtractionService
        {
            public function extract(string $pdfPath): string
            {
                return "15/03/2026;55.00;cotisation;J DUPONT\n16/03/2026;30.00;cotisation;M MARTIN";
            }
        };

        (new ClassifyDocumentIntake($intake->id, 'bank_statement'))->handle(
            $fakeExtractor,
            app(DocumentClassifierService::class),
            app(BankReconciliationService::class),
        );

        $intake->refresh();
        $this->assertSame('routed', $intake->status);
        $this->assertSame('bank_statement', $intake->detected_type);
        $this->assertSame(2, $intake->transactions_created);
        $this->assertDatabaseHas('bank_transactions', ['statement_ref' => 'b.pdf', 'amount' => 55.00]);
    }

    public function test_job_marks_needs_review_when_the_file_is_missing_on_disk(): void
    {
        $intake = DocumentIntake::create(['original_filename' => 'gone.pdf', 'file_path' => 'nowhere/gone.pdf', 'status' => 'processing']);

        (new ClassifyDocumentIntake($intake->id))->handle(
            app(PdfTextExtractionService::class),
            app(DocumentClassifierService::class),
            app(BankReconciliationService::class),
        );

        $this->assertSame('failed', $intake->refresh()->status);
    }

    public function test_job_marks_needs_review_for_a_photo_upload_pending_manual_federation(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('document-intake/uploads/photo.jpg', 'fakejpegbytes');
        $intake = DocumentIntake::create(['original_filename' => 'photo.jpg', 'file_path' => 'document-intake/uploads/photo.jpg', 'status' => 'processing']);

        (new ClassifyDocumentIntake($intake->id))->handle(
            app(PdfTextExtractionService::class),
            app(DocumentClassifierService::class),
            app(BankReconciliationService::class),
        );

        $intake->refresh();
        $this->assertSame('needs_review', $intake->status);
        $this->assertSame('licence_scan', $intake->detected_type);
        $this->assertNull($intake->federation_id);
    }
}
