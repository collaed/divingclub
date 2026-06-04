<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\TripParticipant;
use App\Models\TripReceipt;
use App\Services\TripSettlementService;
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

        return view('trip-settlement.show', compact('event', 'receipts', 'settlement', 'myBalance'));
    }

    public function storeReceipt(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->hasTripSettlement(), 404);
        abort_unless($event->settlement_status === 'open', 403);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:99999',
            'category' => 'required|in:general,transit',
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
        $settlement = $this->service->calculate($event);

        return view('trip-settlement.manage', compact('event', 'pendingReceipts', 'settlement'));
    }

    public function approveReceipt(Request $request, Event $event, TripReceipt $receipt): RedirectResponse
    {
        $this->authorizeBureau();
        abort_unless($event->settlement_status === 'open', 403);

        $data = $request->validate([
            'approved_amount' => 'required|numeric|min:0',
            'category' => 'required|in:general,transit',
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

    public function updateParticipant(Request $request, Event $event, TripParticipant $participant): RedirectResponse
    {
        $this->authorizeBureau();

        $data = $request->validate([
            'driving_percentage' => 'required|integer|min:0|max:100',
            'local_transit_days' => 'required|integer|min:0|max:30',
            'transit_mode' => 'nullable|in:van,own,fly',
        ]);

        $participant->update([
            'driving_percentage' => $data['driving_percentage'],
            'local_transit_days' => $data['local_transit_days'],
        ]);

        if (isset($data['transit_mode'])) {
            EventRegistration::where('event_id', $event->id)
                ->where('user_id', $participant->user_id)
                ->update(['transit_mode' => $data['transit_mode']]);
        }

        return back()->with('success', __('Participant updated.'));
    }

    public function bureauReceipt(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeBureau();
        abort_unless($event->hasTripSettlement(), 404);
        abort_unless($event->settlement_status === 'open', 403);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:99999',
            'category' => 'required|in:general,transit',
            'description' => 'required|string|max:255',
        ]);

        TripReceipt::create([
            'event_id' => $event->id,
            'user_id' => auth()->id(),
            'amount' => $data['amount'],
            'approved_amount' => $data['amount'],
            'category' => $data['category'],
            'description' => $data['description'],
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', __('Expense added.'));
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

    private function authorizeBureau(): void
    {
        abort_unless(auth()->user()->isBureau(), 403);
    }
}
