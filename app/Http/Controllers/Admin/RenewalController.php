<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OverrideRenewalRequest;
use App\Http\Requests\ReceiveRenewalRequest;
use App\Models\PaymentExpected;
use App\Models\Season;
use App\Models\User;
use App\Services\MembershipRenewalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RenewalController extends Controller
{
    public function __construct(private MembershipRenewalService $renewals) {}

    public function index(Request $request): View
    {
        $year = (string) $request->input('season_year', Season::currentDuesYear());

        $rows = $this->renewals->outstanding($year)->map(fn (User $u): array => [
            'user' => $u,
            'proposal' => $this->renewals->proposal($u, $year),
            'lastPaid' => collect($u->detail?->cotisation_years ?? [])->map(fn ($y): string => (string) $y)->sort()->last(),
        ]);

        return view('admin.payments.renewals', [
            'year' => $year,
            'rows' => $rows,
            'total' => $rows->sum(fn (array $r): float => $r['proposal']['amount']),
        ]);
    }

    /**
     * The bureau received money from a member. With no amount, the proposed
     * amount was received; with one, it must match the proposal or exactly one
     * insurance option (or an option the bureau picked among several).
     */
    public function received(ReceiveRenewalRequest $request, User $user): JsonResponse
    {
        $year = (string) $request->input('season_year');
        $proposal = $this->renewals->proposal($user, $year);
        $amount = $request->filled('amount') ? round((float) $request->input('amount'), 2) : $proposal['amount'];

        if (abs($amount - $proposal['amount']) < 0.005 && ! $request->has('insurance')) {
            $this->renewals->markPaid($user, $year, $proposal['amount'], $proposal['components']);

            return $this->paid($amount);
        }

        $matches = collect($this->renewals->matches($user, $year, $amount));
        if ($request->has('insurance')) {
            $matches = $matches->where('insurance', $request->input('insurance') ?: null);
        }

        if ($matches->count() === 1) {
            $match = $matches->first();
            $this->renewals->markPaid($user, $year, $match['amount'], $match['components']);

            return $this->paid($amount);
        }

        $priceList = $this->renewals->priceList($user, $year);
        $ambiguous = $matches->count() > 1;

        return response()->json([
            'ok' => false,
            'ambiguous' => $ambiguous,
            'matches' => $matches->map(fn (array $m): array => ['insurance' => $m['insurance'], 'label' => collect($priceList)->firstWhere('insurance', $m['insurance'])['label'] ?? ''])->values(),
            'message' => $ambiguous
                ? __('€:amount matches several options — pick one.', ['amount' => number_format($amount, 2)])
                : __('€:amount matches nothing this member could owe. Expected: :options', [
                    'amount' => number_format($amount, 2),
                    'options' => collect($priceList)->map(fn (array $p): string => $p['label'].' €'.number_format($p['amount'], 2))->implode(' · '),
                ]),
        ], 422);
    }

    public function insurance(Request $request): View
    {
        $year = (string) $request->input('season_year', Season::currentDuesYear());
        $includeRegistered = $request->boolean('all');
        $queue = $this->renewals->insuranceQueue($year, $includeRegistered);

        return view('admin.payments.insurance', [
            'year' => $year,
            'queue' => $queue,
            'includeRegistered' => $includeRegistered,
            'perTier' => $queue->groupBy(fn (array $r): string => $r['tier']->name)->map->count()->sortKeys(),
        ]);
    }

    public function insuranceRegistered(Request $request, PaymentExpected $payment): JsonResponse
    {
        $payment->update(['insurance_registered_at' => $request->boolean('registered', true) ? now() : null]);

        return response()->json(['ok' => true, 'registered' => $payment->insurance_registered_at !== null]);
    }

    /** "Member X paid for this season" — accepted as is, with how and how much. */
    public function override(OverrideRenewalRequest $request, User $user): JsonResponse
    {
        $year = (string) $request->input('season_year');
        $proposal = $this->renewals->proposal($user, $year);
        $received = $request->filled('amount') ? round((float) $request->input('amount'), 2) : $proposal['amount'];

        $this->renewals->markPaidManually(
            $user, $year, $received, $proposal['amount'], $proposal['components'],
            (string) $request->input('method'), $request->input('note'),
        );

        return $this->paid($received);
    }

    private function paid(float $amount): JsonResponse
    {
        return response()->json(['ok' => true, 'message' => __('Received €:amount — season marked as paid.', ['amount' => number_format($amount, 2)])]);
    }
}
