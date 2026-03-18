<?php

namespace App\Http\Controllers;

use App\Models\BuddyRequest;
use App\Models\BuddyResponse;
use App\Models\DiveSite;
use Illuminate\Http\Request;

class BuddyController extends Controller
{
    public function index()
    {
        $requests = BuddyRequest::active()
            ->with(['user.detail', 'user.certificationLevels.federation', 'diveSite', 'responses.user.detail'])
            ->orderBy('dive_date')
            ->get();
        $sites = DiveSite::active()->orderBy('name')->get();
        return view('buddies.index', compact('requests', 'sites'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'dive_site_id' => 'nullable|exists:dive_sites,id',
            'location_text' => 'nullable|string|max:255',
            'dive_date' => 'required|date|after_or_equal:today',
            'dive_time' => 'nullable|string|max:50',
            'need_type' => 'required|in:buddy,guide,dp',
            'description' => 'nullable|string|max:1000',
            'max_depth' => 'nullable|integer|min:1|max:300',
        ]);
        $data['user_id'] = auth()->id();
        BuddyRequest::create($data);
        return back()->with('success', __('Buddy request posted.'));
    }

    public function respond(Request $request, BuddyRequest $buddyRequest)
    {
        $request->validate(['message' => 'nullable|string|max:500']);

        if ($buddyRequest->user_id === auth()->id()) {
            return back()->with('error', __('Cannot respond to your own request.'));
        }

        BuddyResponse::updateOrCreate(
            ['buddy_request_id' => $buddyRequest->id, 'user_id' => auth()->id()],
            ['message' => $request->message, 'status' => 'interested']
        );
        return back()->with('success', __('Response sent.'));
    }

    public function close(BuddyRequest $buddyRequest)
    {
        abort_unless($buddyRequest->user_id === auth()->id() || auth()->user()->isBureau(), 403);
        $buddyRequest->update(['is_active' => false]);
        return back()->with('success', __('Request closed.'));
    }
}
