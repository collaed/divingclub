<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LedgerTransaction;
use App\Models\User;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Imports the bank's own XLSX account-history export. Chosen over the same
 * bank's CSV export deliberately: the CSV's encoding is inconsistent
 * between exports (Latin-1 on one, UTF-16 on another, seen on real files),
 * while the XLSX stores dates and amounts as real typed values and text as
 * clean Unicode — no guessing. A CSV fallback can be added later if a
 * period is ever only available that way.
 *
 * The 15-column header (date, description, value date, amount, statement
 * number, running balance, operation type, four communication lines,
 * beneficiary account, counterparty name/address/locality) is fixed by the
 * bank's export; an unrecognised header aborts the import rather than
 * guessing column positions.
 */
class LedgerStatementImportService
{
    private const EXPECTED_HEADER = [
        'Date transaction', 'Description', 'Date valeur', 'Montant en EUR', 'Extrait', 'Solde journalier',
        'Opération', 'Communication 1', 'Communication 2', 'Communication 3', 'Communication 4',
        'Compte bénéficiaire', 'Nom de la contrepartie', 'Adresse de la contrepartie', 'Localité de la contrepartie',
    ];

    public function __construct(private LedgerClassificationService $classifier) {}

    /**
     * @return array{imported: int, duplicates: int, statements: array<int, array{statement: string, lines: int, balance_ok: bool}>}
     */
    public function import(string $path, string $originalName, User $uploader): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        // formatData=false: a formatted date would come back as a locale-ambiguous string
        // ("1/5/2026" — day or month first?). Raw mode gives the Excel serial number
        // instead, converted explicitly below — no guessing.
        $rows = $sheet->toArray(null, true, false, false);

        if ($rows === [] || array_slice($rows[0], 0, 15) !== self::EXPECTED_HEADER) {
            throw new \RuntimeException('Unrecognised statement format: the column headers don\'t match the expected export.');
        }

        $imported = 0;
        $duplicates = 0;
        $byStatement = [];

        foreach (array_slice($rows, 1) as $row) {
            if (($row[0] ?? null) === null || $row[0] === '') {
                continue; // blank separator row
            }

            $date = $this->asDate($row[0]);
            $amount = round((float) $row[3], 2);
            $balance = $row[5] !== null && $row[5] !== '' ? round((float) $row[5], 2) : null;
            $statementNo = (string) ($row[4] ?? '');

            $hash = hash('sha256', implode('|', [$date?->toDateString(), $amount, $balance, $statementNo, $row[1] ?? '']));
            if (LedgerTransaction::where('dedup_hash', $hash)->exists()) {
                $duplicates++;
                $byStatement[$statementNo]['lines'] = ($byStatement[$statementNo]['lines'] ?? 0);

                continue;
            }

            $tx = LedgerTransaction::create([
                'transaction_date' => $date,
                'value_date' => $this->asDate($row[2]),
                'amount' => $amount,
                'running_balance' => $balance,
                'statement_no' => $statementNo ?: null,
                'operation_type' => $row[6] ?: null,
                'communication_1' => $row[7] ?: null,
                'communication_2' => $row[8] ?: null,
                'communication_3' => $row[9] ?: null,
                'communication_4' => $row[10] ?: null,
                'beneficiary_account' => $row[11] ?: null,
                'counterparty_name' => $row[12] ?: null,
                'counterparty_address' => $row[13] ?: null,
                'counterparty_locality' => $row[14] ?: null,
                'source_file' => $originalName,
                'dedup_hash' => $hash,
                'imported_by' => $uploader->id,
            ]);
            $this->classifier->classify($tx);

            $imported++;
            $byStatement[$statementNo]['lines'] = ($byStatement[$statementNo]['lines'] ?? 0) + 1;
            $byStatement[$statementNo]['amounts'][] = ['amount' => $amount, 'balance' => $balance];
        }

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'statements' => $this->checkStatements($byStatement),
        ];
    }

    /**
     * Within the imported lines of each statement, verifies that the running
     * balance moves by exactly the line's amount from one line to the next —
     * the same check as the workbook's "Solde début ..." lines, done
     * automatically. Doesn't (can't, from a single import) check the
     * statement's very first line against the previous statement's closing
     * balance.
     *
     * @param  array<string, array{lines?: int, amounts?: array<int, array{amount: float, balance: float|null}>}>  $byStatement
     * @return array<int, array{statement: string, lines: int, balance_ok: bool}>
     */
    private function checkStatements(array $byStatement): array
    {
        $result = [];
        foreach ($byStatement as $statementNo => $data) {
            $amounts = $data['amounts'] ?? [];
            $ok = true;
            for ($i = 1; $i < count($amounts); $i++) {
                [$prevBalance, $curAmount, $curBalance] = [$amounts[$i - 1]['balance'], $amounts[$i]['amount'], $amounts[$i]['balance']];
                if ($prevBalance !== null && $curBalance !== null && abs($prevBalance + $curAmount - $curBalance) > 0.005) {
                    $ok = false;
                    break;
                }
            }
            $result[] = ['statement' => $statementNo, 'lines' => $data['lines'] ?? 0, 'balance_ok' => $ok];
        }

        return $result;
    }

    /** @param  mixed  $value  the raw cell value: an Excel date serial number, or empty */
    private function asDate(mixed $value): ?Carbon
    {
        if (! is_numeric($value)) {
            return null;
        }

        return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
    }
}
