<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LedgerTag;
use Illuminate\Database\Seeder;

/**
 * The approved starter set of ledger tags (see config/ledger.php for the
 * classification rules that use the fixed ones). Idempotent — safe to
 * re-run after adding a rule or a new variable tag.
 */
class LedgerTagSeeder extends Seeder
{
    public function run(): void
    {
        $fixed = [
            ['slug' => 'deposit', 'label' => 'Deposit (acompte)', 'description' => 'Money in for a trip or session, part of a payment schedule.'],
            ['slug' => 'extras', 'label' => 'Extras: drinks / meals', 'description' => 'Small on-site amounts, never part of the deposit schedule.'],
            ['slug' => 'trip_balance', 'label' => 'Trip balance paid back (solde)', 'description' => 'Settlement of a trip.'],
            ['slug' => 'cotisation', 'label' => 'Cotisation', 'description' => 'Membership dues — checked against tariffs and insurance combinations.'],
            ['slug' => 'advance', 'label' => 'Advance for the club (reimbursed)', 'description' => 'A member paid on the club\'s behalf (the club has no bank card): a receipt and a bon à payer are required.'],
            ['slug' => 'federation', 'label' => 'Federation', 'description' => 'FFESSM / FLASSA invoices: licences and dues.'],
            ['slug' => 'bank_fee', 'label' => 'Bank fee', 'description' => 'Monthly account fee, no document needed.'],
            ['slug' => 'course', 'label' => 'Course / certificate', 'description' => 'Nitrox, dive booklet, wetsuit: small member purchases.'],
            ['slug' => 'insurance', 'label' => 'Insurance', 'description' => 'Broker bordereaux, matched to the insurance list.'],
            ['slug' => 'gear', 'label' => 'Gear / equipment', 'description' => 'Purchases for the equipment room.'],
            ['slug' => 'pool_rental', 'label' => 'Pool rental', 'description' => 'Recurring venue invoice, tracked against a yearly budget.'],
            ['slug' => 'gonflage', 'label' => 'Tank inflation (gonflage)', 'description' => 'Periodic invoice from the volunteers who inflate tanks.'],
            ['slug' => 'transport', 'label' => 'Transport / flights', 'description' => 'Coach or airline, tied to a trip.'],
            ['slug' => 'subsidy', 'label' => 'Subsidy', 'description' => 'Expected against the budget.'],
            ['slug' => 'fine', 'label' => 'Fine', 'description' => 'Pass-through: should pair with the payment that clears it.'],
        ];

        $variable = [
            ['slug' => 'for', 'label' => 'For', 'description' => 'Who this payment was actually for, when paid by someone else.'],
            ['slug' => 'covers', 'label' => 'Covers', 'description' => 'How many people or what this amount covers.'],
            ['slug' => 'paid_by', 'label' => 'Paid by', 'description' => 'Who advanced this money on the club\'s behalf.'],
            ['slug' => 'invoice_no', 'label' => 'Invoice no.', 'description' => 'The supplier\'s invoice or reference number.'],
            ['slug' => 'purpose', 'label' => 'Purpose', 'description' => 'What this was for, when no tag fits.'],
        ];

        foreach ($fixed as $i => $tag) {
            LedgerTag::updateOrCreate(['slug' => $tag['slug']], $tag + ['kind' => LedgerTag::KIND_FIXED, 'sort_order' => $i]);
        }
        foreach ($variable as $i => $tag) {
            LedgerTag::updateOrCreate(['slug' => $tag['slug']], $tag + ['kind' => LedgerTag::KIND_VARIABLE, 'sort_order' => $i]);
        }
    }
}
