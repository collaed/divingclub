<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\PaymentExpected;

class AuditorController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('view finances'), 403);

        $season = date('Y');

        $payments = PaymentExpected::with(['user.detail', 'event'])
            ->orderByDesc('created_at')
            ->paginate(50);

        $transactions = BankTransaction::with('matchedPayment.user.detail')
            ->orderByDesc('transaction_date')
            ->paginate(50, ['*'], 'tx_page');

        $summary = [
            'total_due' => PaymentExpected::sum('amount_due'),
            'total_paid' => PaymentExpected::where('status', 'paid')->sum('amount_paid'),
            'outstanding' => PaymentExpected::where('status', 'pending')->sum('amount_due'),
            'matched_tx' => BankTransaction::where('status', 'matched')->count(),
            'unmatched_tx' => BankTransaction::where('status', 'unmatched')->count(),
            'payment_count' => PaymentExpected::count(),
            'paid_count' => PaymentExpected::where('status', 'paid')->count(),
        ];

        return view('admin.auditor.index', compact('payments', 'transactions', 'summary', 'season'));
    }
}
