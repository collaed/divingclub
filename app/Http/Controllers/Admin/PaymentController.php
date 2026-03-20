<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\MembershipFeeComponent;
use App\Models\PaymentExpected;
use App\Models\User;
use App\Services\BankReconciliationService;
use App\Services\FeeCalculationService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = PaymentExpected::with('user.detail')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')->paginate(30)->withQueryString();
        $components = MembershipFeeComponent::orderBy('sort_order')->get();

        return view('admin.payments.index', compact('payments', 'components'));
    }

    public function components()
    {
        $components = MembershipFeeComponent::orderBy('sort_order')->get();

        return view('admin.payments.components', compact('components'));
    }

    public function storeComponent(Request $request)
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

    public function destroyComponent(MembershipFeeComponent $component)
    {
        $component->delete();

        return back()->with('success', __('Component removed.'));
    }

    public function calculateFee(Request $request, User $user)
    {
        $calc = app(FeeCalculationService::class)->calculate($user, $request->get('season', date('Y')), $request->get('optionals', []));

        return back()->with('success', __('Fee: €:amount — :comm', ['amount' => number_format($calc['amount_due'], 2), 'comm' => $calc['communication']]));
    }

    public function generateFee(Request $request, User $user)
    {
        $pe = app(FeeCalculationService::class)->createPaymentExpected($user, $request->get('season', date('Y')), $request->get('optionals', []));

        return back()->with('success', __('Payment expected created: €:amount', ['amount' => number_format($pe->amount_due, 2)]));
    }

    // Bank reconciliation
    public function reconciliation()
    {
        $transactions = BankTransaction::with('matchedPayment.user.detail')->orderByDesc('transaction_date')->paginate(50);
        $summary = [
            'unmatched' => BankTransaction::where('status', 'unmatched')->count(),
            'matched' => BankTransaction::where('status', 'matched')->count(),
            'confirmed' => BankTransaction::where('status', 'confirmed')->count(),
        ];

        return view('admin.payments.reconciliation', compact('transactions', 'summary'));
    }

    public function importStatement(Request $request)
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

    public function suggestMatches()
    {
        $matches = app(BankReconciliationService::class)->suggestMatches();

        return back()->with('success', __(':count matches suggested — please review and confirm.', ['count' => count($matches)]));
    }

    public function confirmMatch(BankTransaction $transaction)
    {
        app(BankReconciliationService::class)->confirmMatch($transaction);

        return back()->with('success', __('Match confirmed.'));
    }

    public function ignoreTransaction(BankTransaction $transaction)
    {
        $transaction->update(['status' => 'ignored']);

        return back()->with('success', __('Transaction ignored.'));
    }
}
