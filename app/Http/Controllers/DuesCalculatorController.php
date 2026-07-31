<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MembershipFee;
use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use App\Models\ThemeSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DuesCalculatorController extends Controller
{
    public function show(): View
    {
        $year = date('Y');
        $statuses = MemberStatus::orderBy('name')->get();
        $fees = MembershipFee::where('season_year', $year)->with('status')->get()->keyBy('status_id');
        $optionals = MembershipFeeComponent::where('is_optional', true)->orderBy('sort_order')->get();

        return view('dues-calculator', compact('year', 'statuses', 'fees', 'optionals'));
    }

    public function calculate(Request $request): View
    {
        $year = $request->input('season_year', date('Y'));
        $statusId = $request->input('status_id');
        $selectedOptionals = $request->input('optionals', []);
        // Merge radio group selections
        foreach ($request->all() as $key => $val) {
            if (str_starts_with($key, 'optionals_') && $val) {
                $selectedOptionals[] = $val;
            }
        }

        $statuses = MemberStatus::orderBy('name')->get();
        $fees = MembershipFee::where('season_year', $year)->with('status')->get()->keyBy('status_id');
        $optionals = MembershipFeeComponent::where('is_optional', true)->orderBy('sort_order')->get();

        $baseFee = $fees[$statusId]?->amount ?? 0;
        $optionalTotal = MembershipFeeComponent::where('is_optional', true)->whereIn('slug', $selectedOptionals)->sum('amount');
        $total = round($baseFee + $optionalTotal, 2);

        $status = $statuses->find($statusId);
        $lastName = strtoupper($request->input('last_name', ''));
        $firstName = strtoupper($request->input('first_name', ''));
        $name = trim("$lastName $firstName");
        $opts = $selectedOptionals ? '+'.implode('+', $selectedOptionals) : '';
        $communication = ThemeSetting::get('club_short_code', config('club.id', 'CLUB'))."-{$year}-{$name}{$opts}";

        $breakdown = [];
        $breakdown[] = ['label' => __('Membership').' ('.($status?->name ?? '—').')', 'amount' => $baseFee];
        foreach (MembershipFeeComponent::where('is_optional', true)->whereIn('slug', $selectedOptionals)->get() as $opt) {
            $breakdown[] = ['label' => $opt->name, 'amount' => $opt->amount];
        }

        return view('dues-calculator', compact('year', 'statuses', 'fees', 'optionals', 'statusId', 'selectedOptionals', 'total', 'communication', 'breakdown', 'lastName', 'firstName'));
    }
}
