<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        return view('admin.analytics.index', [
            'shareUrl' => config('services.umami.share_url'),
            'dashboardUrl' => config('services.umami.url'),
        ]);
    }
}
