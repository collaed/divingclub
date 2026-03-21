<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrialRequest;
use App\Models\TrialRequest;

class TrialController extends Controller
{
    public function show()
    {
        return view('trial.show');
    }

    public function store(StoreTrialRequest $request)
    {
        $data = $request->validated();
        unset($data['website']);

        // Timestamp check
        if (now()->timestamp - (int) $request->input('_ts', 0) < 3) {
            return back()->with('error', __('Please try again.'));
        }

        TrialRequest::create($data);

        return back()->with('success', __('Your request has been submitted! We will contact you to confirm a date and time.'));
    }
}
