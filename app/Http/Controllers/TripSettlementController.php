<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ApproveReceiptRequest;
use App\Http\Requests\BureauReceiptRequest;
use App\Http\Requests\RecordPrepaymentRequest;
use App\Http\Requests\StoreReceiptRequest;
use App\Http\Requests\UpdateDivePricingRequest;
use App\Http\Requests\UpdateParticipantRequest;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PaymentExpected;
use App\Models\TripParticipant;
use App\Models\TripReceipt;
use App\Services\TripSettlementService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripSettlementController extends Controller
{
    public function __construct(private TripSettlementService $service) {}

    // ─── MEMBER: Receipt submission ─────────────────────────────────────

    public function show(Event $event): View
    {
        abort_unless($event->hasTripSettlement(), 404);

        $user = auth()->user();
        $receipts = $event->tripReceipts()->where('user_id', $user->id)->latest()->get();
        $settlement = $this->service->calculate($event);
        $myBalance = collect($settlement['participants'])->firstWhere('user_id', $user->id);

        // Find non-member companions registered by this user
        $companionNames = EventRegistration::where('event_id', $event->id)
            ->where('registered_by', $user->id)
            ->whereNull('user_id')
            ->pluck('non_member_name')
            ->toArray();
        /** @var array<int, array<string, mixed>> $participants */
        $participants = $settlement['participants'];
        $companionBalances = array_values(array_filter(
            $participants,
            fn ($p) => $p['user_id'] === null && in_array($p['name'], $companionNames)
        ));

        return view('trip-settlement.show', compact('event', 'receipts', 'settlement', 'myBalance', 'companionBalances'));
    }

    public function storeReceipt(StoreReceiptRequest $request, Event $event): RedirectResponse
    {
        abort_unless($event->hasTripSettlement(), 404);
        abort_unless($event->settlement_status === 'open', 403);

        $data = $request->validated();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store("trip-receipts/{$event->id}", 'local');
        }

        TripReceipt::create([
            'event_id' => $event->id,
            'user_id' => auth()->id(),
            'amount' => $data['amount'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'image_path' => $imagePath,
            'status' => 'pending',
        ]);

        return back()->with('success', __('Receipt submitted.'));
    }

    public function deleteReceipt(Event $event, TripReceipt $receipt): RedirectResponse
    {
        abort_unless($receipt->user_id === auth()->id() && $receipt->status === 'pending', 403);

        if ($receipt->image_path) {
            Storage::disk('local')->delete($receipt->image_path);
        }
        $receipt->delete();

        return back()->with('success', __('Receipt deleted.'));
    }

    // ─── TREASURER: Management ──────────────────────────────────────────

    public function manage(Event $event): View
    {
        $this->authorizeBureau();
        abort_unless($event->hasTripSettlement(), 404);

        $event->load('tripParticipants.user.detail', 'tripReceipts.user.detail');
        $pendingReceipts = $event->tripReceipts()->where('status', 'pending')->with('user.detail')->get();
        $approvedReceipts = $event->tripReceipts()->whereIn('status', ['approved', 'rejected'])->with('user.detail')->latest()->get();
        $settlement = $this->service->calculate($event);

        return view('trip-settlement.manage', compact('event', 'pendingReceipts', 'approvedReceipts', 'settlement'));
    }

    public function approveReceipt(ApproveReceiptRequest $request, Event $event, TripReceipt $receipt): RedirectResponse
    {
        $this->authorizeBureau();
        abort_unless($event->settlement_status === 'open', 403);

        $data = $request->validated();

        $receipt->update([
            'approved_amount' => $data['approved_amount'],
            'category' => $data['category'],
            'status' => 'approved',
            'reviewer_notes' => $data['reviewer_notes'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', __('Receipt approved.'));
    }

    public function rejectReceipt(Request $request, Event $event, TripReceipt $receipt): RedirectResponse
    {
        $this->authorizeBureau();

        $receipt->update([
            'status' => 'rejected',
            'reviewer_notes' => $request->input('reviewer_notes'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', __('Receipt rejected.'));
    }

    public function updateParticipant(UpdateParticipantRequest $request, Event $event, TripParticipant $participant): JsonResponse|RedirectResponse
    {
        $this->authorizeBureau();

        $data = $request->validated();

        $participant->update([
            'driving_percentage' => $data['driving_percentage'],
            'local_transit_days' => $data['local_transit_days'],
            'van_number' => $data['van_number'] ?? null,
            'supervising_days' => $data['supervising_days'] ?? $participant->supervising_days,
        ]);

        if (isset($data['transit_mode'])) {
            EventRegistration::where('event_id', $event->id)
                ->where('user_id', $participant->user_id)
                ->update(['transit_mode' => $data['transit_mode']]);
        }

        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Participant updated.'));
    }

    public function bureauReceipt(BureauReceiptRequest $request, Event $event): RedirectResponse
    {
        $this->authorizeBureau();
        abort_unless($event->hasTripSettlement(), 404);
        abort_unless($event->settlement_status === 'open', 403);

        $data = $request->validated();
        $isThirdParty = (bool) $request->input('is_third_party');

        $userId = ! empty($data['user_id']) ? (int) $data['user_id'] : null;

        TripReceipt::create([
            'event_id' => $event->id,
            'user_id' => $userId,
            'amount' => $data['amount'],
            'approved_amount' => $data['amount'],
            'category' => $data['category'],
            'description' => $data['description'],
            'is_third_party' => $isThirdParty,
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        if ($data['category'] === 'individual') {
            $this->syncClubOnsitePayments($event);
        }

        return back()->with('success', __('Expense added.'));
    }

    public function updateDayRate(Request $request, Event $event): JsonResponse|RedirectResponse
    {
        $this->authorizeBureau();
        abort_unless($event->settlement_status === 'open', 403);

        $data = $request->validate([
            'local_daily_charge' => 'required|numeric|min:0|max:9999',
        ]);

        $event->update(['local_daily_charge' => $data['local_daily_charge']]);

        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Day rate updated.'));
    }

    public function updateReceipt(Request $request, Event $event, TripReceipt $receipt): RedirectResponse
    {
        $this->authorizeBureau();
        abort_unless($event->settlement_status === 'open', 403);

        $participantIds = $event->tripParticipants()->pluck('user_id')->toArray();
        $isThirdParty = (bool) $request->input('is_third_party');

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:99999',
            'category' => 'required|in:general,transit,diving,individual,memo',
            'description' => 'required|string|max:255',
            'user_id' => $isThirdParty ? 'nullable' : 'required|integer|in:'.implode(',', $participantIds),
            'is_third_party' => 'nullable|boolean',
        ]);

        $receipt->update([
            'amount' => $data['amount'],
            'approved_amount' => $data['amount'],
            'category' => $data['category'],
            'description' => $data['description'],
            'user_id' => $isThirdParty ? null : $data['user_id'],
            'is_third_party' => $isThirdParty,
        ]);

        $this->syncClubOnsitePayments($event);

        return back()->with('success', __('Expense updated.'));
    }

    public function destroyReceipt(Event $event, TripReceipt $receipt): RedirectResponse
    {
        $this->authorizeBureau();
        abort_unless($event->settlement_status === 'open', 403);

        if ($receipt->image_path) {
            Storage::disk('local')->delete($receipt->image_path);
        }
        $receipt->delete();

        $this->syncClubOnsitePayments($event);

        return back()->with('success', __('Expense deleted.'));
    }

    public function updateVans(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeBureau();
        $count = $request->validate(['van_count' => 'required|integer|min:0|max:10'])['van_count'];
        $event->update(['van_count' => $count ?: null]);

        return back()->with('success', __('Van count updated.'));
    }

    public function closeLedger(Event $event): RedirectResponse
    {
        $this->authorizeBureau();
        abort_unless($event->settlement_status === 'open', 403);

        $event->update(['settlement_status' => 'closed']);

        return back()->with('success', __('Ledger closed. No further changes allowed.'));
    }

    public function reopenLedger(Event $event): RedirectResponse
    {
        $this->authorizeBureau();
        $event->update(['settlement_status' => 'open']);

        return back()->with('success', __('Ledger reopened.'));
    }

    public function receiptImage(Event $event, TripReceipt $receipt): mixed
    {
        abort_unless(
            auth()->id() === $receipt->user_id || auth()->user()->isBureau(),
            403
        );

        $path = Storage::disk('local')->path($receipt->image_path);
        abort_unless(file_exists($path), 404);

        return response()->file($path);
    }

    public function breakdown(Event $event): View
    {
        $this->authorizeBureau();
        abort_unless($event->hasTripSettlement(), 404);

        $settlement = $this->service->calculate($event);

        return view('trip-settlement.breakdown', compact('event', 'settlement'));
    }

    public function recordPrepayment(Event $event, RecordPrepaymentRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorizeBureau();
        abort_unless($event->hasTripSettlement(), 404);

        $tp = TripParticipant::where('event_id', $event->id)->findOrFail($request->validated('participant_id'));
        $amount = (float) $request->validated('amount');

        if ($tp->user_id) {
            PaymentExpected::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $tp->user_id, 'type' => 'event'],
                [
                    'season_year' => $event->event_date->format('Y'),
                    'amount_due' => $amount,
                    'amount_paid' => $amount,
                    'status' => $amount > 0 ? 'paid' : 'pending',
                    'paid_at' => $amount > 0 ? now() : null,
                    'communication' => 'PREPAY-'.$event->id.'-'.$tp->user_id,
                ]
            );
        } else {
            $tp->update(['prepaid_amount' => $amount]);
        }

        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Prepayment recorded.'));
    }

    public function export(Event $event): StreamedResponse
    {
        $this->authorizeBureau();
        abort_unless($event->hasTripSettlement(), 404);

        $settlement = $this->service->calculate($event);
        $receipts = $event->tripReceipts()->where('status', 'approved')->with('user.detail')->get();
        $filename = 'settlement-'.$event->id.'-'.now()->format('Y-m-d').'.xlsx';

        $spreadsheet = new Spreadsheet;
        $headerStyle = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '003366']], 'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']]];
        $bold = ['font' => ['bold' => true]];
        $green = ['font' => ['color' => ['rgb' => '008000']]];
        $red = ['font' => ['color' => ['rgb' => 'CC0000']]];
        $grey = ['font' => ['color' => ['rgb' => '999999'], 'italic' => true]];
        $eurFmt = '#,##0.00 €';

        $globalReceipts = $receipts->where('category', 'general');
        $transitReceipts = $receipts->where('category', 'transit');
        $diveInvoice = (float) $receipts->where('category', 'diving')->sum('approved_amount');
        $activeParticipants = collect($settlement['participants'])->where('cancelled', false);
        $vanRiders = $activeParticipants->where('transit_mode', 'van');
        $transitTotal = $settlement['transit_pool'] + $settlement['driver_bounties'];

        // ═══════════════════════════════════════════════════════════════
        // SHEET 1: Summary (pools expanded)
        // ═══════════════════════════════════════════════════════════════
        $s = $spreadsheet->getActiveSheet();
        $s->setTitle(__('Summary'));
        $row = 1;
        $s->setCellValue("A{$row}", $event->title.' — '.__('Trip Settlement'));
        $s->getStyle("A{$row}")->applyFromArray(['font' => ['bold' => true, 'size' => 14]]);
        $row++;
        $s->setCellValue("A{$row}", __('Generated').': '.now()->format('d/m/Y H:i'));
        $s->getStyle("A{$row}")->applyFromArray($grey);

        // Global Pool
        $row += 2;
        $s->setCellValue("A{$row}", __('Global Pool'));
        $s->setCellValue("B{$row}", $settlement['global_pool']);
        $s->getStyle("A{$row}:B{$row}")->applyFromArray($bold);
        $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);
        $row++;
        foreach ($globalReceipts as $r) {
            $s->setCellValue("A{$row}", '  '.$r->description);
            $s->setCellValue("B{$row}", $r->approved_amount);
            $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);
            $row++;
        }
        $s->setCellValue("A{$row}", '  '.__('Division').': ÷ '.$activeParticipants->count().' = ');
        $s->setCellValue("B{$row}", $activeParticipants->count() > 0 ? round($settlement['global_pool'] / $activeParticipants->count(), 2) : 0);
        $s->getStyle("A{$row}:B{$row}")->applyFromArray($grey);
        $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);

        // Transit Pool
        $row += 2;
        $s->setCellValue("A{$row}", __('Transit Pool').' ('.__('incl. bounties').')');
        $s->setCellValue("B{$row}", $transitTotal);
        $s->getStyle("A{$row}:B{$row}")->applyFromArray($bold);
        $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);
        $row++;
        foreach ($transitReceipts as $r) {
            $label = $r->description.($r->user ? ' ('.$r->user->detail?->first_name.')' : '');
            $s->setCellValue("A{$row}", '  '.$label);
            $s->setCellValue("B{$row}", $r->approved_amount);
            $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);
            $row++;
        }
        $s->setCellValue("A{$row}", '  '.__('Driver Bounties'));
        $s->setCellValue("B{$row}", $settlement['driver_bounties']);
        $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);
        $row++;
        $s->setCellValue("A{$row}", '  − '.__('Local Subsidy'));
        $s->setCellValue("B{$row}", -$settlement['local_subsidy']);
        $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);
        $row++;
        $s->setCellValue("A{$row}", '  '.__('Net transit cost'));
        $s->setCellValue("B{$row}", $settlement['net_transit_cost']);
        $s->getStyle("A{$row}:B{$row}")->applyFromArray($bold);
        $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);
        $row++;
        $s->setCellValue("A{$row}", '  '.__('Division').': ÷ '.$vanRiders->count().' '.__('van riders').' = ');
        $s->setCellValue("B{$row}", $vanRiders->count() > 0 ? round($settlement['net_transit_cost'] / $vanRiders->count(), 2) : 0);
        $s->getStyle("A{$row}:B{$row}")->applyFromArray($grey);
        $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);

        // Dive Invoice
        if ($diveInvoice > 0) {
            $row += 2;
            $s->setCellValue("A{$row}", __('Dive Invoice'));
            $s->setCellValue("B{$row}", $diveInvoice);
            $s->getStyle("A{$row}:B{$row}")->applyFromArray($bold);
            $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);
            $row++;
            $totalIndiv = $activeParticipants->sum('individual_charges');
            $s->setCellValue("A{$row}", '  '.__('charged to participants'));
            $s->setCellValue("B{$row}", $totalIndiv);
            $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);
            $row++;
            $s->setCellValue("A{$row}", '  '.__('Delta'));
            $s->setCellValue("B{$row}", $totalIndiv - $diveInvoice);
            $s->getStyle("A{$row}:B{$row}")->applyFromArray($totalIndiv >= $diveInvoice ? $green : $red);
            $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);
        }

        // Net result
        $row += 2;
        $totalPrepaid = collect($settlement['participants'])->sum('prepaid');
        $totalRefunds = collect($settlement['participants'])->where('cancelled', true)->sum('prepaid');
        $netResult = $totalPrepaid - $settlement['global_pool'] - $transitTotal - $diveInvoice - $totalRefunds;
        $s->setCellValue("A{$row}", __('Club Net Result'));
        $s->setCellValue("B{$row}", $netResult);
        $s->getStyle("A{$row}:B{$row}")->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
        $s->getStyle("B{$row}")->applyFromArray($netResult >= 0 ? $green : $red);
        $s->getStyle("B{$row}")->getNumberFormat()->setFormatCode($eurFmt);

        $s->getColumnDimension('A')->setWidth(45);
        $s->getColumnDimension('B')->setAutoSize(true);

        // ═══════════════════════════════════════════════════════════════
        // SHEET 2: Participants
        // ═══════════════════════════════════════════════════════════════
        $ps = $spreadsheet->createSheet();
        $ps->setTitle(__('Participants'));
        $row = 1;
        $pCols = ['A' => __('Name'), 'B' => __('Mode'), 'C' => __('Van'), 'D' => __('Driving %'), 'E' => __('Local Days'), 'F' => __('Dive Costs'), 'G' => __('Balance')];
        foreach ($pCols as $col => $label) {
            $ps->setCellValue("{$col}{$row}", $label);
        }
        $ps->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);

        /** @var Collection<int, TripParticipant> $participants */
        $participants = $event->tripParticipants()->with('user.detail')->get();
        foreach ($participants as $tp) {
            $row++;
            $pResult = $tp->user_id
                ? collect($settlement['participants'])->firstWhere('user_id', $tp->user_id)
                : collect($settlement['participants'])->first(fn ($p) => $p['user_id'] === null && $p['name'] === $tp->non_member_name);
            $ps->setCellValue("A{$row}", $tp->participantName());
            $ps->setCellValue("B{$row}", $pResult['transit_mode'] ?? 'van');
            $ps->setCellValue("C{$row}", $tp->van_number ?: '');
            $ps->setCellValue("D{$row}", $tp->driving_percentage);
            $ps->setCellValue("E{$row}", $tp->local_transit_days);
            $ps->setCellValue("F{$row}", $pResult['individual_charges'] ?? 0);
            $ps->setCellValue("G{$row}", $pResult['balance'] ?? 0);
            if (($pResult['balance'] ?? 0) < 0) {
                $ps->getStyle("G{$row}")->applyFromArray($green);
            } elseif (($pResult['balance'] ?? 0) > 0) {
                $ps->getStyle("G{$row}")->applyFromArray($red);
            }
        }
        $ps->getStyle("F2:G{$row}")->getNumberFormat()->setFormatCode($eurFmt);
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $ps->getColumnDimension($col)->setAutoSize(true);
        }

        // ═══════════════════════════════════════════════════════════════
        // SHEET 3: All Expenses
        // ═══════════════════════════════════════════════════════════════
        $es = $spreadsheet->createSheet();
        $es->setTitle(__('Expenses'));
        $row = 1;
        $eCols = ['A' => __('Member'), 'B' => __('Amount'), 'C' => __('Category'), 'D' => __('Description')];
        foreach ($eCols as $col => $label) {
            $es->setCellValue("{$col}{$row}", $label);
        }
        $es->getStyle("A{$row}:D{$row}")->applyFromArray($headerStyle);

        foreach ($receipts as $r) {
            $row++;
            $es->setCellValue("A{$row}", $r->user ? ($r->user->detail?->first_name.' '.$r->user->detail?->last_name) : __('Club'));
            $es->setCellValue("B{$row}", $r->category === 'individual' ? -$r->approved_amount : $r->approved_amount);
            $es->setCellValue("C{$row}", $r->category);
            $es->setCellValue("D{$row}", $r->description ?? '');
            if ($r->category === 'individual') {
                $es->getStyle("A{$row}:D{$row}")->applyFromArray($green);
            }
        }
        // Driver bounty row
        if ($settlement['driver_bounties'] > 0) {
            $row++;
            $es->setCellValue("A{$row}", __('Club'));
            $es->setCellValue("B{$row}", $settlement['driver_bounties']);
            $es->setCellValue("C{$row}", 'transit');
            /** @var Collection<int, TripParticipant> $drivers */
            $drivers = $event->tripParticipants()->where('driving_percentage', '>', 0)->get();
            $driverList = $drivers->map(fn ($tp) => $tp->participantName().' '.$tp->driving_percentage.'%')->implode(', ');
            $es->setCellValue("D{$row}", __('Driver Bounties').' ('.$driverList.')');
        }
        // Instructor subsidy row
        $totalInstrSubsidy = collect($settlement['participants'])->sum('instructor_subsidy');
        if ($totalInstrSubsidy > 0) {
            $row++;
            $es->setCellValue("A{$row}", __('Club'));
            $es->setCellValue("B{$row}", $totalInstrSubsidy);
            $es->setCellValue("C{$row}", 'diving');
            $instrList = collect($settlement['participants'])->where('instructor_subsidy', '>', 0)
                ->map(fn ($p) => $p['name'].' '.intval($p['instructor_subsidy'] / ($event->instructor_daily_subsidy ?: 1)).'j')
                ->implode(', ');
            $es->setCellValue("D{$row}", __('Instructor subsidy').' ('.$instrList.')');
        }
        $es->getStyle("B2:B{$row}")->getNumberFormat()->setFormatCode($eurFmt);
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $es->getColumnDimension($col)->setAutoSize(true);
        }

        // ═══════════════════════════════════════════════════════════════
        // SHEET 4: Settlement Ledger
        // ═══════════════════════════════════════════════════════════════
        $ls = $spreadsheet->createSheet();
        $ls->setTitle(__('Settlement Ledger'));
        $row = 1;
        $lCols = ['A' => __('Name'), 'B' => __('Mode'), 'C' => __('Global'), 'D' => __('Transit'), 'E' => __('Dive Costs'), 'F' => __('Bounty'), 'G' => __('Instr. Subsidy'), 'H' => __('Prepaid'), 'I' => __('Paid'), 'J' => __('Balance'), 'K' => __('Status')];
        foreach ($lCols as $col => $label) {
            $ls->setCellValue("{$col}{$row}", $label);
        }
        $ls->getStyle("A{$row}:J{$row}")->applyFromArray($headerStyle);

        $dataStart = 2;
        foreach ($settlement['participants'] as $p) {
            $row++;
            $ls->setCellValue("A{$row}", $p['name']);
            $ls->setCellValue("B{$row}", $p['transit_mode']);
            $ls->setCellValue("C{$row}", $p['global_share']);
            $ls->setCellValue("D{$row}", $p['transit_share'] + ($p['local_charge'] ?? 0));
            $ls->setCellValue("E{$row}", $p['individual_charges'] ?? 0);
            $ls->setCellValue("F{$row}", $p['bounty_credit'] > 0 ? -$p['bounty_credit'] : 0);
            $ls->setCellValue("G{$row}", ($p['instructor_subsidy'] ?? 0) > 0 ? -$p['instructor_subsidy'] : 0);
            $ls->setCellValue("H{$row}", $p['prepaid'] > 0 ? -$p['prepaid'] : 0);
            $ls->setCellValue("I{$row}", $p['total_paid'] > 0 ? -$p['total_paid'] : 0);
            $ls->setCellValue("J{$row}", "=C{$row}+D{$row}+E{$row}+F{$row}+G{$row}+H{$row}+I{$row}");
            $ls->setCellValue("K{$row}", ! empty($p['cancelled']) ? __('Cancelled') : __('Active'));
            if (! empty($p['cancelled'])) {
                $ls->getStyle("A{$row}:K{$row}")->applyFromArray(['font' => ['strikethrough' => true, 'color' => ['rgb' => '999999']]]);
            }
        }
        $lastRow = $row;
        $row++;
        $ls->setCellValue("A{$row}", __('TOTALS'));
        foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $ls->setCellValue("{$col}{$row}", "=SUM({$col}{$dataStart}:{$col}{$lastRow})");
        }
        $ls->getStyle("A{$row}:K{$row}")->applyFromArray($bold);
        $ls->getStyle("C{$dataStart}:J{$row}")->getNumberFormat()->setFormatCode($eurFmt);

        // Color balance column (J)
        for ($r = $dataStart; $r <= $lastRow; $r++) {
            $val = $ls->getCell("J{$r}")->getCalculatedValue();
            if ($val > 0) {
                $ls->getStyle("J{$r}")->applyFromArray($red);
            } elseif ($val < 0) {
                $ls->getStyle("J{$r}")->applyFromArray($green);
            }
        }
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'] as $col) {
            $ls->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function updateDivePricing(Event $event, UpdateDivePricingRequest $request): RedirectResponse
    {
        $this->authorizeBureau();
        abort_unless($event->hasTripSettlement(), 404);

        $event->update($request->validated());

        return back()->with('success', __('Dive pricing updated.'));
    }

    /**
     * Auto-maintain a "memo" receipt showing the club advanced money for individual charges.
     * Category "memo" is never counted in any settlement pool — purely for audit trail.
     */
    private function syncClubOnsitePayments(Event $event): void
    {
        $individualReceipts = $event->tripReceipts()
            ->where('status', 'approved')
            ->where('category', 'individual')
            ->where(function ($q): void {
                $q->where('description', 'not like', '%dive%')
                    ->where('description', 'not like', '%plong%')
                    ->where('description', 'not like', '%nitrox%')
                    ->where('description', 'not like', '%EAN%');
            })
            ->with('user.detail')
            ->get();

        $aggregator = $event->tripReceipts()
            ->where('category', 'memo')
            ->where('description', 'like', '[AUTO]%')
            ->first();

        if ($individualReceipts->isEmpty()) {
            $aggregator?->delete();

            return;
        }

        $total = $individualReceipts->sum('approved_amount');
        $lines = $individualReceipts->map(function ($r) {
            $name = $r->user?->detail?->first_name ?? $r->user?->username ?? '?';

            return "{$name}: {$r->description} ({$r->approved_amount} €)";
        })->implode('; ');
        $description = "[AUTO] Club advanced: {$lines}";

        if ($aggregator) {
            $aggregator->update(['amount' => $total, 'approved_amount' => $total, 'description' => $description]);
        } else {
            TripReceipt::create([
                'event_id' => $event->id,
                'user_id' => null,
                'amount' => $total,
                'approved_amount' => $total,
                'category' => 'memo',
                'description' => $description,
                'is_third_party' => false,
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        }
    }

    private function authorizeBureau(): void
    {
        abort_unless(auth()->user()->isBureau(), 403);
    }
}
