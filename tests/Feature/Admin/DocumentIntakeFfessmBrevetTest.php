<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Jobs\ClassifyDocumentIntake;
use App\Models\CertificationLevel;
use App\Models\DocumentIntake;
use App\Models\Federation;
use App\Models\MemberDetail;
use App\Models\User;
use App\Services\BankReconciliationService;
use App\Services\DocumentClassifierService;
use App\Services\PdfTextExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Same real-export layout as FfessmBrevetImportServiceTest, routed through
 * the Document Intake upload endpoint instead of calling the service
 * directly — the standalone FFESSM Brevets Import page was removed in favor
 * of this dedicated drop area.
 */
class DocumentIntakeFfessmBrevetTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private Federation $ffessm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->ffessm = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'FFESSM', 'visibility' => 'active']);
        CertificationLevel::create(['federation_id' => $this->ffessm->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);
    }

    /** @param  array<int, array<int, mixed>>  $dataRows */
    private function makeXlsx(array $dataRows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'brevets_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('B3', 'Liste des brevets - 06-97-0240 - CLUB EUROPEEN DE PLONGEE');
        $sheet->setCellValue('B4', 'Saison 2025/2026');
        $sheet->fromArray(['Date Obtention', 'Date Enregistrement', 'Lieu', 'Niveau', 'Type', 'Comité', 'Structure', null, 'Plongeur', null, null, 'Moniteur', null], null, 'A8');
        foreach ($dataRows as $i => $row) {
            $sheet->fromArray($row, null, 'A'.(9 + $i));
        }
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_uploading_a_clean_brevets_file_routes_and_applies_certs(): void
    {
        $user = User::factory()->create();
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Benjamin', 'last_name' => 'Accadia']);
        $path = $this->makeXlsx([
            ['06/05/2026', '07/05/2026', 'LUXEMBOURG', 'Niveau 1', 'Normal', '06', '06970240', 'CLUB EUROPEEN DE PLONGEE', 'A-26-4309891', 'Mr', 'ACCADIA BENJAMIN', 'A-09-428497', 'GIANCOLA PIETRO'],
        ]);

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.document-intake.store'), [
                'intake_type' => 'ffessm_brevet',
                'files' => [new UploadedFile($path, 'brevets.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)],
            ])
            ->assertRedirect();

        $intake = DocumentIntake::sole();
        $this->assertSame('routed', $intake->status);
        $this->assertSame('ffessm_brevet', $intake->detected_type);
        $this->assertSame(1, $intake->import_result['certs_created']);
        $this->assertTrue($user->certificationLevels()->where('code', 'N1')->exists());
        @unlink($path);
    }

    public function test_an_unmatched_name_lands_in_needs_review_with_the_name_recorded(): void
    {
        $path = $this->makeXlsx([
            ['06/05/2026', '07/05/2026', 'LUXEMBOURG', 'Niveau 1', 'Normal', '06', '06970240', 'CLUB EUROPEEN DE PLONGEE', 'A-26-4309891', 'Mr', 'NOBODY HERE', 'A-09-428497', 'GIANCOLA PIETRO'],
        ]);

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.document-intake.store'), [
                'intake_type' => 'ffessm_brevet',
                'files' => [new UploadedFile($path, 'brevets.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)],
            ]);

        $intake = DocumentIntake::sole();
        $this->assertSame('needs_review', $intake->status);
        $this->assertSame(['NOBODY HERE'], $intake->import_result['unmatched_names']);
        $this->assertNotNull($intake->error);
        @unlink($path);
    }

    public function test_an_unrecognised_header_fails_cleanly(): void
    {
        Storage::fake('local');
        $path = tempnam(sys_get_temp_dir(), 'brevets_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray(['Not', 'The', 'Right', 'Header'], null, 'A1');
        (new Xlsx($spreadsheet))->save($path);
        Storage::disk('local')->put('document-intake/uploads/bad.xlsx', file_get_contents($path));
        @unlink($path);

        $intake = DocumentIntake::create(['original_filename' => 'bad.xlsx', 'file_path' => 'document-intake/uploads/bad.xlsx', 'status' => 'processing']);

        (new ClassifyDocumentIntake($intake->id, 'ffessm_brevet'))->handle(
            app(PdfTextExtractionService::class),
            app(DocumentClassifierService::class),
            app(BankReconciliationService::class),
        );

        $intake->refresh();
        $this->assertSame('failed', $intake->status);
        $this->assertNotNull($intake->error);
    }

    public function test_upload_rejects_a_non_xlsx_file(): void
    {
        Bus::fake();

        $this->actingAs($this->createBureauUser())
            ->post(route('admin.document-intake.store'), [
                'intake_type' => 'ffessm_brevet',
                'files' => [UploadedFile::fake()->create('brevets.pdf', 10, 'application/pdf')],
            ])
            ->assertSessionHasErrors('files.0');
    }
}
