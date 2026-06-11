<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PaymentExpected;
use App\Models\TripParticipant;
use App\Models\TripReceipt;
use App\Services\TripSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

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

    public function storeReceipt(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->hasTripSettlement(), 404);
        abort_unless($event->settlement_status === 'open', 403);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:99999',
            'category' => 'required|in:general,transit,diving,individual',
            'description' => 'nullable|string|max:255',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

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

    public function approveReceipt(Request $request, Event $event, TripReceipt $receipt): RedirectResponse
    {
        $this->authorizeBureau();
        abort_unless($event->settlement_status === 'open', 403);

        $data = $request->validate([
            'approved_amount' => 'required|numeric|min:0',
            'category' => 'required|in:general,transit,diving,individual',
            'reviewer_notes' => 'nullable|string|max:500',
        ]);

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

    public function updateParticipant(Request $request, Event $event, TripParticipant $participant): JsonResponse|RedirectResponse
    {
        $this->authorizeBureau();

        $tripDays = $event->event_date->diffInDays($event->end_date ?? $event->event_date) ?: 1;

        $data = $request->validate([
            'driving_percentage' => 'required|integer|min:0|max:100',
            'local_transit_days' => 'required|integer|min:0|max:'.$tripDays,
            'transit_mode' => 'nullable|in:van,own,fly',
            'van_number' => 'nullable|integer|min:1|max:10',
        ]);

        $participant->update([
            'driving_percentage' => $data['driving_percentage'],
            'local_transit_days' => $data['local_transit_days'],
            'van_number' => $data['van_number'] ?? null,
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

    public function bureauReceipt(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeBureau();
        abort_unless($event->hasTripSettlement(), 404);
        abort_unless($event->settlement_status === 'open', 403);

        $participantIds = $event->tripParticipants()->whereNotNull('user_id')->pluck('user_id')->toArray();
        $isThirdParty = (bool) $request->input('is_third_party');
        $category = $request->input('category');

        $userRule = $category === 'individual'
            ? 'required|integer|in:'.implode(',', $participantIds)
            : 'nullable|integer|in:'.implode(',', $participantIds);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:99999',
            'category' => 'required|in:general,transit,diving,individual',
            'description' => 'required|string|max:255',
            'user_id' => $userRule,
            'is_third_party' => 'nullable|boolean',
        ]);

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
            'category' => 'required|in:general,transit,diving,individual',
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

    public function recordPrepayment(Event $event, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeBureau();
        abort_unless($event->hasTripSettlement(), 404);

        $request->validate([
            'participant_id' => 'required|integer',
            'amount' => 'required|numeric|min:0',
        ]);

        $tp = TripParticipant::where('event_id', $event->id)->findOrFail($request->participant_id);

        if ($tp->user_id) {
            PaymentExpected::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $tp->user_id, 'type' => 'event'],
                [
                    'season_year' => $event->event_date->format('Y'),
                    'amount_due' => $request->amount,
                    'amount_paid' => $request->amount,
                    'status' => $request->amount > 0 ? 'paid' : 'pending',
                    'paid_at' => $request->amount > 0 ? now() : null,
                    'communication' => 'PREPAY-'.$event->id.'-'.$tp->user_id,
                ]
            );
        } else {
            $tp->update(['prepaid_amount' => $request->amount]);
        }

        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Prepayment recorded.'));
    }

    private function authorizeBureau(): void
    {
        abort_unless(auth()->user()->isBureau(), 403);
    }
}
