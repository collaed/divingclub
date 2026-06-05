<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\MembershipFeeComponent;
use App\Models\PaymentExpected;
use App\Models\User;
use App\Services\BankReconciliationService;
use App\Services\FeeCalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request): RedirectResponse|View
    {
        $query = PaymentExpected::with('user.detail')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('search'), fn ($q, $s) => $q->where(function ($w) use ($s): void {
                $w->where('communication', 'ILIKE', "%{$s}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('primary_email', 'ILIKE', "%{$s}%")
                        ->orWhereHas('detail', fn ($dq) => $dq->where('last_name', 'ILIKE', "%{$s}%")
                            ->orWhere('first_name', 'ILIKE', "%{$s}%")));
            }));

        $sortable = ['created_at', 'amount_due', 'amount_paid', 'status', 'type'];
        $sort = in_array($request->input('sort'), $sortable) ? $request->input('sort') : 'created_at';
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        $payments = $query->orderBy($sort, $dir)->paginate($this->perPage(30))->withQueryString();
        $components = MembershipFeeComponent::orderBy('sort_order')->get();

        return view('admin.payments.index', compact('payments', 'components'));
    }

    public function components(): RedirectResponse|View
    {
        $components = MembershipFeeComponent::orderBy('sort_order')->get();

        return view('admin.payments.components', compact('components'));
    }

    public function storeComponent(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'is_base' => 'boolean',
            'is_optional' => 'boolean',
            'description' => 'nullable|string',
        ]);
        $v['is_base'] = $request->boolean('is_base');
        $v['is_optional'] = $request->boolean('is_optional');
        MembershipFeeComponent::create($v);

        return back()->with('success', __('Component added.'));
    }

    public function destroyComponent(MembershipFeeComponent $component): RedirectResponse|View
    {
        $component->delete();

        return back()->with('success', __('Component removed.'));
    }

    public function calculateFee(Request $request, User $user): RedirectResponse|View
    {
        $calc = app(FeeCalculationService::class)->calculate($user, $request->get('season', date('Y')), $request->get('optionals', []));

        return back()->with('success', __('Fee: €:amount — :comm', ['amount' => number_format((float) $calc['amount_due'], 2), 'comm' => $calc['communication']]));
    }

    public function generateFee(Request $request, User $user): RedirectResponse|View
    {
        $pe = app(FeeCalculationService::class)->createPaymentExpected($user, $request->get('season', date('Y')), $request->get('optionals', []));

        return back()->with('success', __('Payment expected created: €:amount', ['amount' => number_format((float) $pe->amount_due, 2)]));
    }

    public function generateBulkFees(Request $request): RedirectResponse|View
    {
        $season = $request->get('season', date('Y'));
        $svc = app(FeeCalculationService::class);

        $users = User::whereHas('status', fn ($q) => $q->where('slug', 'actif'))
            ->whereDoesntHave('paymentsExpected', fn ($q) => $q->where('type', 'membership')->where('season_year', $season))
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $svc->createPaymentExpected($user, $season);
            $count++;
        }

        return back()->with('success', __(':count membership fees generated for season :season.', ['count' => $count, 'season' => $season]));
    }

    public function adjustComponents(Request $request, PaymentExpected $payment): RedirectResponse|View
    {
        $request->validate([
            'components' => 'required|array',
            'components.*.label' => 'required|string',
            'components.*.amount' => 'required|numeric|min:0',
        ]);

        $components = $request->components;
        $total = collect($components)->sum('amount');

        $payment->update([
            'components' => $components,
            'amount_due' => round($total, 2),
        ]);

        return back()->with('success', __('Components adjusted. New total: €:amount', ['amount' => number_format((float) $total, 2)]));
    }

    // Bank reconciliation
    public function reconciliation(): RedirectResponse|View
    {
        $transactions = BankTransaction::with('matchedPayment.user.detail')->orderByDesc('transaction_date')->paginate(50);
        $summary = [
            'unmatched' => BankTransaction::where('status', 'unmatched')->count(),
            'matched' => BankTransaction::where('status', 'matched')->count(),
            'confirmed' => BankTransaction::where('status', 'confirmed')->count(),
        ];

        return view('admin.payments.reconciliation', compact('transactions', 'summary'));
    }

    public function importStatement(Request $request): RedirectResponse
    {
        $request->validate([
            'statement' => 'required_without:statement_pdf|nullable|string',
            'statement_pdf' => 'required_without:statement|nullable|file|mimes:pdf|max:10240',
            'statement_ref' => 'nullable|string|max:100',
        ]);

        $svc = app(BankReconciliationService::class);

        if ($request->hasFile('statement_pdf')) {
            $path = $request->file('statement_pdf')->store('bank-statements', 'local');
            $result = $svc->parsePdfStatement(storage_path('app/'.$path), $request->statement_ref);

            return back()->with('success', __(':count transactions imported from PDF (:pages pages).', [
                'count' => count($result['transactions']),
                'pages' => $result['page_count'],
            ]));
        }

        $txs = $svc->parseStatement($request->statement);

        return back()->with('success', __(':count transactions imported.', ['count' => count($txs)]));
    }

    public function suggestMatches(): RedirectResponse
    {
        $matches = app(BankReconciliationService::class)->suggestMatches();

        return back()->with('success', __(':count matches suggested — please review and confirm.', ['count' => count($matches)]));
    }

    public function confirmMatch(BankTransaction $transaction): RedirectResponse
    {
        app(BankReconciliationService::class)->confirmMatch($transaction);

        return back()->with('success', __('Match confirmed.'));
    }

    public function ignoreTransaction(BankTransaction $transaction): RedirectResponse
    {
        $transaction->update(['status' => 'ignored']);

        return back()->with('success', __('Transaction ignored.'));
    }
}
