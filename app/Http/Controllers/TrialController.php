<?php

namespace App\Http\Controllers;

use App\Models\TrialRequest;
use Illuminate\Http\Request;

class TrialController extends Controller
{
    public function show()
    {
        return view('trial.show');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'preferred_date' => 'nullable|date|after:today',
            'message' => 'nullable|string|max:1000',
            // Honeypot
            'website' => 'max:0',
        ]);
        unset($data['website']);

        // Timestamp check
        if (now()->timestamp - (int)$request->input('_ts', 0) < 3) {
            return back()->with('error', __('Please try again.'));
        }

        TrialRequest::create($data);
        return back()->with('success', __('Your request has been submitted! We will contact you to confirm a date and time.'));
    }
}
