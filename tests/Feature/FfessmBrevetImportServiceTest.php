<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CertificationLevel;
use App\Models\Federation;
use App\Models\MemberDetail;
use App\Models\MemberLicence;
use App\Models\User;
use App\Services\FfessmBrevetImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Builds a real .xlsx matching the FFESSM "Liste des brevets" export exactly
 * as confirmed against real club exports: several blank rows, a "Saison
 * YYYY/YYYY" banner, then the header row (its exact row number varies by
 * export, so the service locates it by content), then data rows with
 * text-formatted dd/mm/yyyy dates (not Excel date serials — confirmed via
 * PhpSpreadsheet against a real file).
 */
class FfessmBrevetImportServiceTest extends TestCase
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
        CertificationLevel::create(['federation_id' => $this->ffessm->id, 'code' => 'RIFAP', 'name' => 'RIFA Plongée', 'category' => 'specialty', 'rank' => 230]);
        CertificationLevel::create(['federation_id' => $this->ffessm->id, 'code' => 'APNEA_PASS', 'name' => 'Pass Apnéiste (ATP)', 'category' => 'diver', 'rank' => 5]);
    }

    /** @param  array<int, array<int, mixed>>  $dataRows */
    private function makeXlsx(array $dataRows, string $season = 'Saison 2025/2026'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'brevets_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('B3', 'Liste des brevets - 06-97-0240 - CLUB EUROPEEN DE PLONGEE');
        $sheet->setCellValue('B4', $season);
        $sheet->fromArray(['Date Obtention', 'Date Enregistrement', 'Lieu', 'Niveau', 'Type', 'Comité', 'Structure', null, 'Plongeur', null, null, 'Moniteur', null], null, 'A8');
        foreach ($dataRows as $i => $row) {
            $sheet->fromArray($row, null, 'A'.(9 + $i));
        }
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function member(string $first, string $last): User
    {
        $user = User::factory()->create();
        MemberDetail::create(['user_id' => $user->id, 'first_name' => $first, 'last_name' => $last]);

        return $user;
    }

    public function test_a_real_row_is_applied_to_the_matching_member(): void
    {
        $this->member('Benjamin', 'Accadia');
        $path = $this->makeXlsx([
            ['06/05/2026', '07/05/2026', 'LUXEMBOURG', 'Niveau 1', 'Normal', '06', '06970240', 'CLUB EUROPEEN DE PLONGEE', 'A-26-4309891', 'Mr', 'ACCADIA BENJAMIN', 'A-09-428497', 'GIANCOLA PIETRO'],
        ]);

        $result = app(FfessmBrevetImportService::class)->import($path, 'test.xlsx');

        $this->assertSame('2025-2026', $result['season']);
        $this->assertSame(1, $result['rows']);
        $this->assertSame(1, $result['certs_created']);
        $this->assertSame(1, $result['licences_updated']);
        $this->assertSame([], $result['unmatched_names']);

        $user = User::whereHas('detail', fn ($q) => $q->where('first_name', 'Benjamin'))->firstOrFail();
        $cert = $user->certificationLevels()->where('code', 'N1')->first();
        $this->assertNotNull($cert);
        $this->assertSame('2026-05-06', $cert->pivot->obtained_date);

        $licence = MemberLicence::where('user_id', $user->id)->where('federation_id', $this->ffessm->id)->first();
        $this->assertSame('A-26-4309891', $licence->licence_number);
        $this->assertSame('2025-2026', $licence->season);
        @unlink($path);
    }

    public function test_name_word_order_does_not_matter(): void
    {
        $this->member('Keran', 'Chaussard');
        $path = $this->makeXlsx([
            ['06/02/2026', '08/02/2026', 'Merl, Luxembourg', 'Niveau 1', 'Normal', '06', '06970240', 'CLUB EUROPEEN DE PLONGEE', 'A-20-923394', 'Mr', 'CHAUSSARD Keran', 'A-03-042391', 'TONGIO JEROME'],
        ]);

        $result = app(FfessmBrevetImportService::class)->import($path, 'test.xlsx');

        $this->assertSame(1, $result['certs_created']);
        @unlink($path);
    }

    public function test_a_hyphenated_double_surname_matches_the_exports_space_separated_form(): void
    {
        $user = User::factory()->create();
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Barthélémy', 'last_name' => 'Puente-Castan']);
        $path = $this->makeXlsx([
            ['26/04/2026', '26/04/2026', 'Strasbourg', 'Niveau 1', 'Normal', '06', '06970240', 'CLUB EUROPEEN DE PLONGEE', 'A-24-1048401', 'Mr', 'PUENTE CASTAN BARTHELEMY', 'A-20-923394', 'CHAUSSARD Keran'],
        ]);

        $result = app(FfessmBrevetImportService::class)->import($path, 'test.xlsx');

        $this->assertSame(1, $result['certs_created']);
        $this->assertSame([], $result['unmatched_names']);
        @unlink($path);
    }

    public function test_reimporting_the_same_row_updates_rather_than_duplicates(): void
    {
        $this->member('Benjamin', 'Accadia');
        $row = ['06/05/2026', '07/05/2026', 'LUXEMBOURG', 'Niveau 1', 'Normal', '06', '06970240', 'CLUB EUROPEEN DE PLONGEE', 'A-26-4309891', 'Mr', 'ACCADIA BENJAMIN', 'A-09-428497', 'GIANCOLA PIETRO'];
        $path1 = $this->makeXlsx([$row]);
        app(FfessmBrevetImportService::class)->import($path1, 'test.xlsx');
        @unlink($path1);

        $path2 = $this->makeXlsx([$row]);
        $result = app(FfessmBrevetImportService::class)->import($path2, 'test.xlsx');

        $this->assertSame(0, $result['certs_created']);
        $this->assertSame(1, $result['certs_updated']);
        $user = User::whereHas('detail', fn ($q) => $q->where('first_name', 'Benjamin'))->firstOrFail();
        $this->assertSame(1, $user->certificationLevels()->count());
        @unlink($path2);
    }

    /**
     * Real exports sometimes print the freediving level without its "(ATP)"
     * suffix — confirmed against a real 2020-2021 season export where "Pass
     * Apnéiste" (no suffix) was wrongly reported as an unrecognised level.
     */
    public function test_the_apnea_pass_level_matches_with_or_without_its_atp_suffix(): void
    {
        $this->member('Marco', 'Testeur');
        $path = $this->makeXlsx([
            ['06/05/2026', '07/05/2026', 'LUXEMBOURG', 'Pass Apnéiste', 'Normal', '06', '06970240', 'CLUB EUROPEEN DE PLONGEE', 'A-26-4309891', 'Mr', 'TESTEUR MARCO', 'A-09-428497', 'GIANCOLA PIETRO'],
        ]);

        $result = app(FfessmBrevetImportService::class)->import($path, 'test.xlsx');

        $this->assertSame(1, $result['certs_created']);
        $this->assertSame([], $result['unmatched_levels']);
        $user = User::whereHas('detail', fn ($q) => $q->where('first_name', 'Marco'))->firstOrFail();
        $this->assertNotNull($user->certificationLevels()->where('code', 'APNEA_PASS')->first());
        @unlink($path);
    }

    public function test_an_unknown_name_is_reported_and_left_untouched(): void
    {
        $path = $this->makeXlsx([
            ['06/05/2026', '07/05/2026', 'LUXEMBOURG', 'Niveau 1', 'Normal', '06', '06970240', 'CLUB EUROPEEN DE PLONGEE', 'A-26-4309891', 'Mr', 'NOBODY HERE', 'A-09-428497', 'GIANCOLA PIETRO'],
        ]);

        $result = app(FfessmBrevetImportService::class)->import($path, 'test.xlsx');

        $this->assertSame(0, $result['certs_created']);
        $this->assertSame(['NOBODY HERE'], $result['unmatched_names']);
        @unlink($path);
    }

    public function test_an_ambiguous_name_shared_by_two_members_is_reported_and_left_untouched(): void
    {
        // Two members with the exact same first+last name — the row's
        // normalized name key matches both, so neither is touched.
        $this->member('Jerome', 'Boisseau');
        $this->member('Jerome', 'Boisseau');
        $path = $this->makeXlsx([
            ['06/05/2026', '07/05/2026', 'LUXEMBOURG', 'Niveau 1', 'Normal', '06', '06970240', 'CLUB EUROPEEN DE PLONGEE', 'A-26-4309891', 'Mr', 'BOISSEAU JEROME', 'A-09-428497', 'GIANCOLA PIETRO'],
        ]);

        $result = app(FfessmBrevetImportService::class)->import($path, 'test.xlsx');

        $this->assertSame(0, $result['certs_created']);
        $this->assertSame(['BOISSEAU JEROME'], $result['ambiguous_names']);
        @unlink($path);
    }

    public function test_an_unrecognised_level_is_reported_and_left_untouched(): void
    {
        $this->member('Benjamin', 'Accadia');
        $path = $this->makeXlsx([
            ['06/05/2026', '07/05/2026', 'LUXEMBOURG', 'Niveau Fantome', 'Normal', '06', '06970240', 'CLUB EUROPEEN DE PLONGEE', 'A-26-4309891', 'Mr', 'ACCADIA BENJAMIN', 'A-09-428497', 'GIANCOLA PIETRO'],
        ]);

        $result = app(FfessmBrevetImportService::class)->import($path, 'test.xlsx');

        $this->assertSame(0, $result['certs_created']);
        $this->assertSame(['Niveau Fantome'], $result['unmatched_levels']);
        @unlink($path);
    }

    public function test_an_unrecognised_header_is_refused(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'brevets_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray(['Not', 'The', 'Right', 'Header'], null, 'A1');
        (new Xlsx($spreadsheet))->save($path);

        $this->expectException(\RuntimeException::class);
        app(FfessmBrevetImportService::class)->import($path, 'test.xlsx');
        @unlink($path);
    }
}
