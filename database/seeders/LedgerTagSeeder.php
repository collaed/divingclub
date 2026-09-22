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
        // 'direction' is the tag's real-world money expectation (green/red on the
        // chip), not the classification rule's matching breadth in config('ledger.rules')
        // — a rule can match either sign while the tag itself always means one
        // direction. null where there genuinely isn't one (fine: either side of its
        // own pass-through pair can be tagged with it).
        $fixed = [
            ['slug' => 'deposit', 'label' => 'Deposit (acompte)', 'direction' => LedgerTag::DIRECTION_IN, 'description' => 'Money in for a trip or session, part of a payment schedule.'],
            ['slug' => 'extras', 'label' => 'Extras: drinks / meals', 'direction' => LedgerTag::DIRECTION_IN, 'description' => 'Small on-site amounts, never part of the deposit schedule.'],
            ['slug' => 'trip_balance', 'label' => 'Trip balance paid back (solde)', 'direction' => LedgerTag::DIRECTION_OUT, 'description' => 'Settlement of a trip.'],
            ['slug' => 'cotisation', 'label' => 'Cotisation', 'direction' => LedgerTag::DIRECTION_IN, 'description' => 'Membership dues — checked against tariffs and insurance combinations.'],
            ['slug' => 'advance', 'label' => 'Advance for the club (reimbursed)', 'direction' => LedgerTag::DIRECTION_OUT, 'description' => 'A member paid on the club\'s behalf (the club has no bank card): a receipt and a bon à payer are required.'],
            ['slug' => 'federation', 'label' => 'Federation', 'direction' => LedgerTag::DIRECTION_OUT, 'description' => 'FFESSM / FLASSA invoices: licences and dues.'],
            ['slug' => 'bank_fee', 'label' => 'Bank fee', 'direction' => LedgerTag::DIRECTION_OUT, 'description' => 'Monthly account fee, no document needed.'],
            ['slug' => 'course', 'label' => 'Course / certificate', 'direction' => LedgerTag::DIRECTION_IN, 'description' => 'Nitrox, dive booklet, wetsuit: small member purchases.'],
            ['slug' => 'insurance', 'label' => 'Insurance', 'direction' => LedgerTag::DIRECTION_OUT, 'description' => 'Broker bordereaux, matched to the insurance list.'],
            ['slug' => 'gear', 'label' => 'Gear / equipment', 'direction' => LedgerTag::DIRECTION_OUT, 'description' => 'Purchases for the equipment room.'],
            ['slug' => 'pool_rental', 'label' => 'Pool rental', 'direction' => LedgerTag::DIRECTION_OUT, 'description' => 'Recurring venue invoice, tracked against a yearly budget.'],
            ['slug' => 'gonflage', 'label' => 'Tank inflation (gonflage)', 'direction' => LedgerTag::DIRECTION_OUT, 'description' => 'Periodic invoice from the volunteers who inflate tanks.'],
            ['slug' => 'transport', 'label' => 'Transport / flights', 'direction' => LedgerTag::DIRECTION_OUT, 'description' => 'Coach or airline, tied to a trip.'],
            ['slug' => 'subsidy', 'label' => 'Subsidy', 'direction' => LedgerTag::DIRECTION_IN, 'description' => 'Expected against the budget.'],
            ['slug' => 'fine', 'label' => 'Fine', 'direction' => null, 'description' => 'Pass-through: should pair with the payment that clears it.'],
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
            LedgerTag::updateOrCreate(['slug' => $tag['slug']], $tag + ['kind' => LedgerTag::KIND_VARIABLE, 'direction' => null, 'sort_order' => $i]);
        }
    }
}
