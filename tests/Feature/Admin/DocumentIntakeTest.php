<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Jobs\ClassifyDocumentIntake;
use App\Jobs\ProcessLicenceScan;
use App\Models\BankTransaction;
use App\Models\DocumentIntake;
use App\Models\Federation;
use App\Services\BankReconciliationService;
use App\Services\DocumentClassifierService;
use App\Services\PdfTextExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

    public function test_uploading_to_the_licence_scan_zone_with_a_federation_routes_immediately(): void
    {
        Bus::fake([ProcessLicenceScan::class]);
        $fed = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'FFESSM', 'visibility' => 'active']);

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.document-intake.store'), [
                'intake_type' => 'licence_scan',
                'federation_id' => $fed->id,
                'files' => [UploadedFile::fake()->image('card.jpg')],
            ])
            ->assertRedirect();

        $intake = DocumentIntake::sole();
        $this->assertSame('routed', $intake->status);
        $this->assertNotNull($intake->licence_scan_id);
    }

    public function test_the_licence_scan_zone_accepts_a_scanned_pdf_not_just_photos(): void
    {
        Bus::fake([ProcessLicenceScan::class]);
        $fed = Federation::create(['acronym' => 'FLASSA', 'full_name' => 'FLASSA', 'visibility' => 'active']);

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.document-intake.store'), [
                'intake_type' => 'licence_scan',
                'federation_id' => $fed->id,
                'files' => [UploadedFile::fake()->create('card.pdf', 50, 'application/pdf')],
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('routed', DocumentIntake::sole()->status);
    }

    public function test_uploading_to_the_licence_scan_zone_without_a_federation_still_needs_review(): void
    {
        $this->actingAs($this->createBureauUser())
            ->post(route('admin.document-intake.store'), [
                'intake_type' => 'licence_scan',
                'files' => [UploadedFile::fake()->image('card.jpg')],
            ])
            ->assertRedirect();

        $intake = DocumentIntake::sole();
        $this->assertSame('needs_review', $intake->status);
        $this->assertSame('licence_scan', $intake->detected_type);
        $this->assertNull($intake->federation_id);
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

    /** Same bank export header as BankReconciliationXlsxImportTest. */
    private const BANK_HEADER = [
        'Date transaction', 'Description', 'Date valeur', 'Montant en EUR', 'Extrait', 'Solde journalier',
        'Opération', 'Communication 1', 'Communication 2', 'Communication 3', 'Communication 4',
        'Compte bénéficiaire', 'Nom de la contrepartie', 'Adresse de la contrepartie', 'Localité de la contrepartie',
    ];

    /** @param  array<int, array<int, mixed>>  $rows */
    private function makeBankXlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bankrecon_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::BANK_HEADER, null, 'A1');
        foreach ($rows as $i => $row) {
            $sheet->fromArray($row, null, 'A'.($i + 2));
            if (array_key_exists(0, $row)) {
                $sheet->setCellValue('A'.($i + 2), Date::PHPToExcel($row[0]));
            }
        }
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_uploading_an_xlsx_bank_statement_routes_and_creates_transactions(): void
    {
        $path = $this->makeBankXlsx([
            [strtotime('2026-03-17'), 'Virement', null, 153.5, '1', 24721.77, 'VIR', 'CLUB-2026-3-DUPONT', 'MARIE', null, null, 'LU680019335575380000', 'DUPONT MARIE', 'RUE CLAIE 95', '6792 HALANZY'],
        ]);

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.document-intake.store'), [
                'intake_type' => 'bank_statement',
                'files' => [new UploadedFile($path, 'extrait.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)],
            ])
            ->assertRedirect();

        $intake = DocumentIntake::sole();
        $this->assertSame('routed', $intake->status);
        $this->assertSame(1, $intake->transactions_created);
        $this->assertDatabaseHas('bank_transactions', ['statement_ref' => 'extrait.xlsx', 'amount' => 153.5]);
        @unlink($path);
    }

    public function test_an_xlsx_bank_statement_with_the_wrong_header_fails_cleanly(): void
    {
        Storage::fake('local');
        $path = tempnam(sys_get_temp_dir(), 'bankrecon_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray(['Not', 'The', 'Right', 'Header'], null, 'A1');
        (new Xlsx($spreadsheet))->save($path);
        Storage::disk('local')->put('document-intake/uploads/bad.xlsx', file_get_contents($path));
        @unlink($path);

        $intake = DocumentIntake::create(['original_filename' => 'bad.xlsx', 'file_path' => 'document-intake/uploads/bad.xlsx', 'status' => 'processing']);

        (new ClassifyDocumentIntake($intake->id, 'bank_statement'))->handle(
            app(PdfTextExtractionService::class),
            app(DocumentClassifierService::class),
            app(BankReconciliationService::class),
        );

        $intake->refresh();
        $this->assertSame('failed', $intake->status);
        $this->assertNotNull($intake->error);
        $this->assertSame(0, BankTransaction::count());
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
