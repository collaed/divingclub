<?php

/**
 * Middleware: block new member registration when license is invalid.
 *
 * Applied only to the registration routes. Existing members can still
 * log in and use the system — only new sign-ups are gated.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;

class CheckLicense
{
    /**
     * Block new member registration when license is invalid and member count exceeds free tier.
     * Existing members can still log in and use the system.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! LicenseService::isValid()) {
            return redirect()->route('home')
                ->with('error', __('This installation has exceeded the free tier limit of 100 members. A valid license key is required. Please contact your administrator.'));
        }

        return $next($request);
    }
}
