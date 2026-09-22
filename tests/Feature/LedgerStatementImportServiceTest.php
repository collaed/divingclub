<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LedgerCounterparty;
use App\Models\LedgerTransaction;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\User;
use App\Services\LedgerStatementImportService;
use Database\Seeders\LedgerTagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Builds a real .xlsx with the bank's exact 15-column header (confirmed
 * against real exports) and real typed dates/amounts, matching how
 * PhpSpreadsheet actually serves them (Excel date serials in raw mode —
 * formatted mode gives a locale-ambiguous string like "1/5/2026" and was
 * rejected for that reason; see LedgerStatementImportService).
 */
#[Group('p1')]
class LedgerStatementImportServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private const HEADER = [
        'Date transaction', 'Description', 'Date valeur', 'Montant en EUR', 'Extrait', 'Solde journalier',
        'Opération', 'Communication 1', 'Communication 2', 'Communication 3', 'Communication 4',
        'Compte bénéficiaire', 'Nom de la contrepartie', 'Adresse de la contrepartie', 'Localité de la contrepartie',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(LedgerTagSeeder::class);
    }

    /** @param  array<int, array<int, mixed>>  $rows */
    private function makeXlsx(array $rows, array $header = self::HEADER): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ledger_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($header, null, 'A1');
        foreach ($rows as $i => $row) {
            $sheet->fromArray($row, null, 'A'.($i + 2));
            if (array_key_exists(0, $row)) {
                $sheet->setCellValue('A'.($i + 2), Date::PHPToExcel($row[0]));
            }
            if (array_key_exists(2, $row)) {
                $sheet->setCellValue('C'.($i + 2), Date::PHPToExcel($row[2]));
            }
        }
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function uploader(): User
    {
        return User::factory()->create();
    }

    public function test_a_real_row_is_imported_with_typed_dates_and_amounts(): void
    {
        $path = $this->makeXlsx([
            [strtotime('2026-01-05'), 'FAGNY DOMINIQUE', strtotime('2026-01-04'), 153.5, '1', 24721.77, 'VIR', 'Cotisation et RC Dominique Fagny', null, null, null, null, 'FAGNY DOMINIQUE', 'RUE CLAIE 95', '6792 HALANZY'],
        ]);

        $result = app(LedgerStatementImportService::class)->import($path, 'test.xlsx', $this->uploader());

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['duplicates']);
        $tx = LedgerTransaction::firstOrFail();
        $this->assertSame('2026-01-05', $tx->transaction_date->toDateString());
        $this->assertSame('2026-01-04', $tx->value_date->toDateString());
        $this->assertEqualsWithDelta(153.5, (float) $tx->amount, 0.001);
        $this->assertEqualsWithDelta(24721.77, (float) $tx->running_balance, 0.001);
        $this->assertSame('FAGNY DOMINIQUE', $tx->counterparty_name);
        @unlink($path);
    }

    public function test_accented_text_survives_the_round_trip(): void
    {
        $path = $this->makeXlsx([
            [strtotime('2026-01-08'), 'COLLART EDDY', strtotime('2026-01-08'), -80.91, '1', 24260.27, 'VIR', 'Remboursement retrait carte', 'effectué par Al Maha Rent-a-Car', null, null, 'LU680019335575380000', 'COLLART EDDY', null, null],
        ]);

        app(LedgerStatementImportService::class)->import($path, 'test.xlsx', $this->uploader());

        $tx = LedgerTransaction::firstOrFail();
        $this->assertSame('effectué par Al Maha Rent-a-Car', $tx->communication_2);
        @unlink($path);
    }

    public function test_reimporting_the_same_statement_is_a_noop(): void
    {
        $path = $this->makeXlsx([
            [strtotime('2026-01-05'), 'FAGNY DOMINIQUE', strtotime('2026-01-04'), 153.5, '1', 24721.77, 'VIR', 'Cotisation', null, null, null, null, 'FAGNY DOMINIQUE', null, null],
        ]);
        $uploader = $this->uploader();
        app(LedgerStatementImportService::class)->import($path, 'test.xlsx', $uploader);

        $result = app(LedgerStatementImportService::class)->import($path, 'test.xlsx', $uploader);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['duplicates']);
        $this->assertSame(1, LedgerTransaction::count());
        @unlink($path);
    }

    public function test_an_unrecognised_header_is_refused(): void
    {
        $path = $this->makeXlsx([['x']], ['Not', 'The', 'Right', 'Header']);

        $this->expectException(\RuntimeException::class);
        app(LedgerStatementImportService::class)->import($path, 'test.xlsx', $this->uploader());
        @unlink($path);
    }

    public function test_a_broken_running_balance_fails_the_statement_check(): void
    {
        $path = $this->makeXlsx([
            [strtotime('2026-01-05'), 'A', strtotime('2026-01-05'), 100, '1', 1000, 'VIR', null, null, null, null, null, 'A', null, null],
            [strtotime('2026-01-06'), 'B', strtotime('2026-01-06'), 50, '1', 1200, 'VIR', null, null, null, null, null, 'B', null, null], // should be 1050
        ]);

        $result = app(LedgerStatementImportService::class)->import($path, 'test.xlsx', $this->uploader());

        $this->assertFalse($result['statements'][0]['balance_ok']);
        @unlink($path);
    }

    public function test_a_correct_running_balance_passes_the_statement_check(): void
    {
        $path = $this->makeXlsx([
            [strtotime('2026-01-05'), 'A', strtotime('2026-01-05'), 100, '1', 1000, 'VIR', null, null, null, null, null, 'A', null, null],
            [strtotime('2026-01-06'), 'B', strtotime('2026-01-06'), 50, '1', 1050, 'VIR', null, null, null, null, null, 'B', null, null],
        ]);

        $result = app(LedgerStatementImportService::class)->import($path, 'test.xlsx', $this->uploader());

        $this->assertTrue($result['statements'][0]['balance_ok']);
        @unlink($path);
    }

    public function test_an_imported_membership_payment_that_matches_a_commitment_is_expected(): void
    {
        $status = MemberStatus::firstOrCreate(['slug' => 'externe'], ['name' => 'Externe']);
        $user = User::factory()->create(['status_id' => $status->id]);
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Marie', 'last_name' => 'Dupont']);
        PaymentExpected::create(['user_id' => $user->id, 'type' => 'membership', 'season_year' => '2027', 'amount_due' => 190, 'components' => [], 'status' => 'pending']);

        $path = $this->makeXlsx([
            [strtotime('2026-01-05'), 'DUPONT MARIE', strtotime('2026-01-05'), 190, '1', 1000, 'VIR', 'Cotisation 2027', null, null, null, null, 'DUPONT MARIE', null, null],
        ]);

        app(LedgerStatementImportService::class)->import($path, 'test.xlsx', $this->uploader());

        $tx = LedgerTransaction::firstOrFail();
        $this->assertSame(LedgerTransaction::STATE_EXPECTED, $tx->state);
        $this->assertNotNull($tx->counterparty);
        $this->assertSame($user->id, $tx->counterparty->member_id);
        @unlink($path);
    }

    public function test_an_unknown_payer_with_no_recognisable_purpose_is_unknown(): void
    {
        $path = $this->makeXlsx([
            [strtotime('2026-01-15'), 'COMMISSION EUROPEENNE', strtotime('2026-01-15'), -1600, '1', 22603.36, 'VIR', '5988 - CARES-CONF-2025-12', null, null, null, 'LU14', 'COMMISSION EUROPEENNE', null, null],
        ]);

        app(LedgerStatementImportService::class)->import($path, 'test.xlsx', $this->uploader());

        $this->assertSame(LedgerTransaction::STATE_UNKNOWN, LedgerTransaction::firstOrFail()->state);
        @unlink($path);
    }

    public function test_a_known_supplier_with_a_matched_keyword_but_no_amount_to_check_is_recognised(): void
    {
        LedgerCounterparty::create(['name' => 'RECETTE COMMUNALE DE STEINFORT', 'kind' => LedgerCounterparty::KIND_VENUE]);
        $path = $this->makeXlsx([
            [strtotime('2026-01-08'), 'RECETTE COMMUNALE DE STEINFORT', strtotime('2026-01-08'), -272.71, '1', 24341.18, 'VIR', 'Facture 1844', null, null, null, 'LU96', 'RECETTE COMMUNALE DE STEINFORT', null, null],
        ]);

        app(LedgerStatementImportService::class)->import($path, 'test.xlsx', $this->uploader());

        $tx = LedgerTransaction::firstOrFail();
        $this->assertSame(LedgerTransaction::STATE_RECOGNISED, $tx->state);
        $this->assertSame('52', $tx->category);
        $this->assertTrue($tx->tags->contains('slug', 'pool_rental'));
        @unlink($path);
    }

    public function test_a_juan_les_pins_deposit_suggests_the_group_without_assigning_it(): void
    {
        $path = $this->makeXlsx([
            [strtotime('2026-03-04'), 'KRAEMER ROGER', strtotime('2026-03-04'), 500, '1', 23209.37, 'VIR', '2eme acompte sejour Juan-les-Pins', null, null, null, null, 'KRAEMER ROGER', null, null],
        ]);

        app(LedgerStatementImportService::class)->import($path, 'test.xlsx', $this->uploader());

        $tx = LedgerTransaction::firstOrFail();
        $this->assertSame('Juan-les-Pins', $tx->suggested_group);
        $this->assertNull($tx->operation_id);
        @unlink($path);
    }
}
