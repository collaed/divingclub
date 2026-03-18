<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrialRequest;
use Illuminate\Http\Request;

class TrialRequestController extends Controller
{
    public function index()
    {
        $requests = TrialRequest::orderByDesc('created_at')->get();
        return view('admin.trial-requests.index', compact('requests'));
    }

    public function update(Request $request, TrialRequest $trialRequest)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled',
            'confirmed_date' => 'nullable|date',
            'admin_notes' => 'nullable|string|max:1000',
        ]);
        if ($data['status'] === 'confirmed') $data['confirmed_by'] = auth()->id();
        $trialRequest->update($data);
        return back()->with('success', __('Trial request updated.'));
    }
}
