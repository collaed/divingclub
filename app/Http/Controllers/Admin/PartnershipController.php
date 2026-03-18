<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClubPartnership;
use App\Models\ExternalRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class PartnershipController extends Controller
{
    public function index()
    {
        $partners = ClubPartnership::withCount('externalRegistrations')->orderBy('name')->get();
        return view('admin.partnerships.index', compact('partners'));
    }

    public function create()
    {
        $keys = ClubPartnership::generateKeyPair();
        return view('admin.partnerships.create', compact('keys'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'base_url' => 'required|url',
            'api_key_id' => 'required|string',
            'api_secret' => 'required|string',
            'their_api_key_id' => 'nullable|string',
            'their_api_secret' => 'nullable|string',
        ]);

        ClubPartnership::create([
            'name' => $data['name'],
            'base_url' => rtrim($data['base_url'], '/'),
            'api_key_id' => $data['api_key_id'],
            'api_secret_hash' => Hash::make($data['api_secret']),
            'their_api_key_id' => $data['their_api_key_id'],
            'their_api_secret' => $data['their_api_secret'] ? Crypt::encryptString($data['their_api_secret']) : null,
        ]);

        return redirect()->route('admin.partnerships.index')->with('success', 'Partnership created. Share the Key ID and Secret with the partner club.');
    }

    public function destroy(ClubPartnership $partnership)
    {
        $partnership->delete();
        return back()->with('success', 'Partnership removed.');
    }

    /**
     * Fetch federated events from a partner club and display them.
     */
    public function remoteEvents(ClubPartnership $partnership)
    {
        if (!$partnership->their_api_key_id || !$partnership->their_api_secret) {
            return back()->with('error', 'Outbound API credentials not configured for this partner.');
        }

        try {
            $response = Http::withHeaders([
                'X-Club-Key-Id' => $partnership->their_api_key_id,
                'X-Club-Secret' => Crypt::decryptString($partnership->their_api_secret),
            ])->timeout(10)->get($partnership->base_url . '/api/federation/events');

            $events = $response->successful() ? $response->json('events', []) : [];
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to connect: ' . $e->getMessage());
        }

        return view('admin.partnerships.remote-events', compact('partnership', 'events'));
    }

    /**
     * Manage external registrations for our events.
     */
    public function registrations(Request $request)
    {
        $regs = ExternalRegistration::with(['event', 'partnership'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.partnerships.registrations', compact('regs'));
    }

    public function approveRegistration(ExternalRegistration $registration)
    {
        $registration->update(['status' => 'approved']);
        return back()->with('success', $registration->external_member_name . ' approved.');
    }

    public function rejectRegistration(ExternalRegistration $registration)
    {
        $registration->update(['status' => 'rejected']);
        return back()->with('success', $registration->external_member_name . ' rejected.');
    }
}
