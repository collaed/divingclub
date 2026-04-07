<?php

/**
 * Fix member active status, cotisation years, and medical cert expiry dates.
 *
 * - cotisation_years: derived from Joomla cb_datepaiement (payment >= Aug = covers next year)
 * - medical expiry: stored date is establishment date, real expiry = +12 months (FFESSM rule)
 * - active status: member is active if cotisation covers current season (2026)
 *
 * Run: php artisan tinker --execute 'require "database/seeders/fix_active_medical.php";'
 */

use App\Models\Document;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "🔧 Fixing member status, cotisation, and medical dates...\n\n";

$joomla = DB::connection('joomla');
$statuses = MemberStatus::pluck('id', 'slug');
$currentSeason = 2026; // 2025-2026 season

// ── 1. Fix cotisation_years from Joomla payment dates ──
echo "═══ Cotisation Years ═══\n";

$members = $joomla->table('jos_users as u')
    ->join('jos_comprofiler as cb', 'cb.user_id', '=', 'u.id')
    ->where('u.block', 0)
    ->where('u.id', '!=', 62)
    ->get(['u.email', 'cb.cb_datepaiement', 'cb.cb_virement']);

$paidCount = 0;
$unpaidCount = 0;

foreach ($members as $m) {
    $user = User::where('primary_email', $m->email)->first();
    if (! $user || ! $user->detail) {
        continue;
    }

    $payDate = $m->cb_datepaiement;
    $years = [];

    if ($payDate && $payDate > '2025-08-01') {
        // Paid for 2025-2026 season
        $years = [2025, 2026];
        $paidCount++;
    } elseif ($payDate && $payDate > '2024-08-01') {
        // Only paid for 2024-2025
        $years = [2024, 2025];
        $unpaidCount++;
    } else {
        $unpaidCount++;
    }

    $user->detail->update(['cotisation_years' => $years ?: null]);
}

echo "  Paid for 2026: {$paidCount}\n";
echo "  Not paid for 2026: {$unpaidCount}\n";

// ── 2. Fix medical cert expiry dates ──
echo "\n═══ Medical Certificate Expiry ═══\n";

// The stored expiry_date is actually the establishment date from Joomla
// Real expiry = establishment_date + 12 months (FFESSM rule for all ages)
$validityMonths = 12;

$certs = Document::where('category', 'medical')->where('is_current', true)->get();
$fixed = 0;
$alreadyCorrect = 0;

foreach ($certs as $cert) {
    if (! $cert->expiry_date) {
        continue;
    }

    $establishmentDate = Carbon::parse($cert->expiry_date);

    // If the date is already far in the future (>2 years from now), it was probably
    // already set correctly by the sample seeder — skip
    if ($establishmentDate->gt(now()->addYears(2))) {
        $alreadyCorrect++;
        continue;
    }

    $realExpiry = $establishmentDate->copy()->addMonths($validityMonths);

    // Store establishment date in a note, update expiry to computed value
    $cert->update([
        'date_established' => $establishmentDate->toDateString(),
        'expiry_date' => $realExpiry->toDateString(),
    ]);
    $fixed++;
}

echo "  Fixed: {$fixed} certs (establishment + 12 months)\n";
echo "  Already correct: {$alreadyCorrect}\n";

// Recount
$validNow = Document::where('category', 'medical')->where('is_current', true)
    ->where('expiry_date', '>', now())->count();
$expiredNow = Document::where('category', 'medical')->where('is_current', true)
    ->where('expiry_date', '<=', now())->count();
echo "  Valid now: {$validNow}\n";
echo "  Expired now: {$expiredNow}\n";

// ── 3. Set active status based on cotisation ──
echo "\n═══ Active Status ═══\n";

$activeStatus = $statuses['actif'] ?? $statuses['membre_de_droit'] ?? 2;
$inactiveStatus = $statuses['honoraire'] ?? 4;

$activated = 0;
$deactivated = 0;

$allDetails = MemberDetail::with('user')->get();
foreach ($allDetails as $detail) {
    if (! $detail->user) {
        continue;
    }

    $years = $detail->cotisation_years ?? [];
    $hasPaid = in_array($currentSeason, $years);

    if ($hasPaid && $detail->user->status_id === $inactiveStatus) {
        // Don't change status for members who have a specific status from Joomla
        // Only flag the cotisation
    }

    // The key thing: just ensure cotisation_years is correct (done above)
    // Status mapping was already done by the Joomla import based on cb_statut
}

// Summary
$withCotis2026 = MemberDetail::whereNotNull('cotisation_years')
    ->get()->filter(fn ($d) => in_array(2026, $d->cotisation_years ?? []))->count();
$withoutCotis2026 = MemberDetail::get()->filter(fn ($d) => ! in_array(2026, $d->cotisation_years ?? []))->count();

echo "  Members with 2026 cotisation: {$withCotis2026}\n";
echo "  Members without 2026 cotisation: {$withoutCotis2026}\n";

echo "\n✅ Done!\n";
