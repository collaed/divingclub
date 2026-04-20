<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClubPartnership;
use App\Models\ExternalRegistration;
use App\Models\ThemeSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class PartnershipController extends Controller
{
    public function index(): RedirectResponse|View
    {
        $partners = ClubPartnership::withCount('externalRegistrations')->orderBy('name')->get();

        return view('admin.partnerships.index', compact('partners'));
    }

    public function create(): RedirectResponse|View
    {
        $keys = ClubPartnership::generateKeyPair();

        return view('admin.partnerships.create', compact('keys'));
    }

    public function store(Request $request): RedirectResponse|View
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

    public function destroy(ClubPartnership $partnership): RedirectResponse|View
    {
        $partnership->delete();

        return back()->with('success', 'Partnership removed.');
    }

    /**
     * Fetch federated events from a partner club and display them.
     */
    public function remoteEvents(ClubPartnership $partnership): RedirectResponse|View
    {
        if (! $partnership->their_api_key_id || ! $partnership->their_api_secret) {
            return back()->with('error', 'Outbound API credentials not configured for this partner.');
        }

        try {
            $response = Http::withHeaders([
                'X-Club-Key-Id' => $partnership->their_api_key_id,
                'X-Club-Secret' => Crypt::decryptString($partnership->their_api_secret),
            ])->timeout(10)->get($partnership->base_url.'/api/federation/events');

            $events = $response->successful() ? $response->json('events', []) : [];
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to connect: '.$e->getMessage());
        }

        return view('admin.partnerships.remote-events', compact('partnership', 'events'));
    }

    /**
     * Manage external registrations for our events.
     */
    public function registrations(Request $request): RedirectResponse|View
    {
        $regs = ExternalRegistration::with(['event', 'partnership'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.partnerships.registrations', compact('regs'));
    }

    public function approveRegistration(ExternalRegistration $registration): RedirectResponse
    {
        $registration->update(['status' => 'approved']);
        $this->notifyExternalMember($registration, 'approved');

        return back()->with('success', $registration->external_member_name.' approved.');
    }

    public function rejectRegistration(ExternalRegistration $registration): RedirectResponse
    {
        $registration->update(['status' => 'rejected']);
        $this->notifyExternalMember($registration, 'rejected');

        return back()->with('success', $registration->external_member_name.' rejected.');
    }

    private function notifyExternalMember(ExternalRegistration $reg, string $status): void
    {
        if (! $reg->external_member_email) {
            return;
        }

        $clubName = ThemeSetting::get('club_full_name', config('app.name'));
        $event = $reg->event;

        $body = $status === 'approved'
            ? __("Dear :name,\n\nYour registration for \":event\" on :date has been approved by :club.\n\nLocation: :location\n\nWe look forward to seeing you!\n:club", [
                'name' => $reg->external_member_name,
                'event' => $event->title,
                'date' => $event->event_date->format('d/m/Y'),
                'location' => $event->location ?? '—',
                'club' => $clubName,
            ])
            : __("Dear :name,\n\nUnfortunately, your registration for \":event\" on :date could not be accepted by :club.\n\nPlease contact us if you have questions.\n:club", [
                'name' => $reg->external_member_name,
                'event' => $event->title,
                'date' => $event->event_date->format('d/m/Y'),
                'club' => $clubName,
            ]);

        $subject = $status === 'approved'
            ? __('Registration Approved — :event', ['event' => $event->title])
            : __('Registration Update — :event', ['event' => $event->title]);

        Mail::raw($body, fn ($m) => $m->to($reg->external_member_email)->subject($subject));
    }
}
