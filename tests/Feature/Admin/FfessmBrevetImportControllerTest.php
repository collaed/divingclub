<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CertificationLevel;
use App\Models\Federation;
use App\Models\MemberDetail;
use App\Models\MemberLicence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class FfessmBrevetImportControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $fed = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'FFESSM', 'visibility' => 'active']);
        CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);
    }

    private function makeXlsx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'brevets_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('B4', 'Saison 2025/2026');
        $sheet->fromArray(['Date Obtention', 'Date Enregistrement', 'Lieu', 'Niveau', 'Type', 'Comité', 'Structure', null, 'Plongeur', null, null, 'Moniteur', null], null, 'A8');
        $sheet->fromArray(['06/05/2026', '07/05/2026', 'LUXEMBOURG', 'Niveau 1', 'Normal', '06', '06970240', 'CLUB EUROPEEN DE PLONGEE', 'A-26-4309891', 'Mr', 'ACCADIA BENJAMIN', 'A-09-428497', 'GIANCOLA PIETRO'], null, 'A9');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_a_plain_member_is_forbidden(): void
    {
        $this->actingAs($this->createMemberUser())
            ->get(route('admin.ffessm-brevet-import.index'))
            ->assertForbidden();
    }

    public function test_a_bureau_master_can_open_the_page(): void
    {
        $this->actingAs($this->createBureauUser())
            ->get(route('admin.ffessm-brevet-import.index'))
            ->assertOk();
    }

    public function test_uploading_a_real_export_applies_it_and_flashes_a_summary(): void
    {
        $user = User::factory()->create();
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Benjamin', 'last_name' => 'Accadia']);
        $path = $this->makeXlsx();

        $response = $this->actingAs($this->createBureauUser())
            ->post(route('admin.ffessm-brevet-import.store'), [
                'files' => [new UploadedFile($path, 'brevets.xlsx', null, null, true)],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(1, $user->certificationLevels()->count());
        $this->assertSame('A-26-4309891', MemberLicence::where('user_id', $user->id)->first()->licence_number);
        @unlink($path);
    }
}
