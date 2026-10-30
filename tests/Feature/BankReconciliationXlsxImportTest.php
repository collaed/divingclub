<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Services\BankReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Same bank export format as LedgerStatementImportServiceTest, now also
 * accepted by Bank Reconciliation's "Excel Upload" tab — preferred over
 * the PDF Upload tab for the same reason: typed dates/amounts and a real
 * IBAN, no OCR text to parse.
 */
#[Group('p1')]
class BankReconciliationXlsxImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = [
        'Date transaction', 'Description', 'Date valeur', 'Montant en EUR', 'Extrait', 'Solde journalier',
        'Opération', 'Communication 1', 'Communication 2', 'Communication 3', 'Communication 4',
        'Compte bénéficiaire', 'Nom de la contrepartie', 'Adresse de la contrepartie', 'Localité de la contrepartie',
    ];

    /** @param  array<int, array<int, mixed>>  $rows */
    private function makeXlsx(array $rows, array $header = self::HEADER): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bankrecon_').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($header, null, 'A1');
        foreach ($rows as $i => $row) {
            $sheet->fromArray($row, null, 'A'.($i + 2));
            if (array_key_exists(0, $row)) {
                $sheet->setCellValue('A'.($i + 2), Date::PHPToExcel($row[0]));
            }
        }
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_an_incoming_transfer_is_imported_with_iban_and_communication(): void
    {
        $path = $this->makeXlsx([
            [strtotime('2026-03-17'), 'Virement', null, 153.5, '1', 24721.77, 'VIR', 'CLUB-2026-3-DUPONT', 'MARIE', null, null, 'LU680019335575380000', 'DUPONT MARIE', 'RUE CLAIE 95', '6792 HALANZY'],
        ]);

        $result = app(BankReconciliationService::class)->parseXlsxStatement($path, 'EXTRACT-1');

        $this->assertCount(1, $result['transactions']);
        $tx = BankTransaction::firstOrFail();
        $this->assertSame('2026-03-17', $tx->transaction_date->toDateString());
        $this->assertEqualsWithDelta(153.5, (float) $tx->amount, 0.001);
        $this->assertSame('CLUB-2026-3-DUPONT MARIE DUPONT MARIE', $tx->communication);
        $this->assertSame('LU680019335575380000', $tx->counterparty);
        $this->assertSame('EXTRACT-1', $tx->statement_ref);
        @unlink($path);
    }

    public function test_an_outgoing_transfer_is_not_imported(): void
    {
        $path = $this->makeXlsx([
            [strtotime('2026-03-17'), 'Virement', null, -80.91, '1', 24260.27, 'VIR', 'Remboursement', null, null, null, 'LU68', 'COLLART EDDY', null, null],
        ]);

        $result = app(BankReconciliationService::class)->parseXlsxStatement($path);

        $this->assertSame([], $result['transactions']);
        $this->assertSame(0, BankTransaction::count());
        @unlink($path);
    }

    public function test_an_unrecognised_header_is_refused(): void
    {
        $path = $this->makeXlsx([['x']], ['Not', 'The', 'Right', 'Header']);

        $this->expectException(\RuntimeException::class);
        app(BankReconciliationService::class)->parseXlsxStatement($path);
        @unlink($path);
    }
}
