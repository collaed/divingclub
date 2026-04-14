<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EmailStatsService;
use Illuminate\Http\Request;

class EmailStatsController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->get('date', now()->toDateString());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = now()->toDateString();
        }
        $stats = EmailStatsService::forDate($date);

        return view('admin.email-stats.index', [
            'date' => $date,
            'subjects' => $stats['subjects'],
            'totals' => $stats['totals'],
        ]);
    }
}
