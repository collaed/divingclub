<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubPartnership;
use App\Models\Event;
use App\Models\ExternalRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class FederationApiController extends Controller
{
    /**
     * Authenticate inbound API request from a partner club.
     */
    private function authenticate(Request $request): ?ClubPartnership
    {
        $keyId = $request->header('X-Club-Key-Id');
        $secret = $request->header('X-Club-Secret');

        if (! $keyId || ! $secret) {
            return null;
        }

        $partner = ClubPartnership::where('api_key_id', $keyId)->where('is_active', true)->first();
        if (! $partner || ! Hash::check($secret, $partner->api_secret_hash)) {
            return null;
        }

        return $partner;
    }

    /**
     * GET /api/federation/events — list federated events visible to partners.
     */
    public function events(Request $request): JsonResponse
    {
        $partner = $this->authenticate($request);
        if (! $partner) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $events = Event::where('is_federated', true)
            ->where('event_date', '>=', now()->toDateString())
            ->where('status', 'published')
            ->orderBy('event_date')
            ->get()
            ->map(fn (Event $e): array => [
                'id' => $e->id,
                'title' => $e->title,
                'event_date' => $e->event_date,
                'event_time' => $e->event_time,
                'end_date' => $e->end_date,
                'location' => $e->location,
                'description' => $e->description,
                'event_type' => $e->event_type,
                'external_slots' => $e->external_slots,
                'slots_taken' => $e->externalRegistrations()->whereIn('status', ['pending', 'approved'])->count(),
                'estimated_cost' => $e->estimated_cost,
                'levels_display' => $e->levels_display,
            ]);

        $partner->update(['last_sync_at' => now()]);

        return response()->json(['events' => $events]);
    }

    /**
     * POST /api/federation/register — register an external member for a federated event.
     */
    public function register(Request $request): JsonResponse
    {
        $partner = $this->authenticate($request);
        if (! $partner) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'event_id' => 'required|integer',
            'member_name' => 'required|string|max:200',
            'member_email' => 'nullable|email',
            'member_iban' => 'nullable|string|max:34',
            'cert_level' => 'nullable|string|max:100',
            'medical_valid_until' => 'nullable|date',
            'external_ref' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $event = Event::where('id', $data['event_id'])->where('is_federated', true)->first();
        if (! $event) {
            return response()->json(['error' => 'Event not found or not federated'], 404);
        }

        // Check slots
        $taken = $event->externalRegistrations()->whereIn('status', ['pending', 'approved'])->count();
        if ($event->external_slots > 0 && $taken >= $event->external_slots) {
            return response()->json(['error' => 'No external slots available'], 409);
        }

        $reg = ExternalRegistration::create([
            'event_id' => $event->id,
            'partnership_id' => $partner->id,
            'external_member_name' => $data['member_name'],
            'external_member_email' => $data['member_email'] ?? null,
            'external_member_iban' => $data['member_iban'] ?? null,
            'external_member_phone' => $data['member_phone'] ?? null,
            'external_member_federation' => $data['member_federation'] ?? null,
            'external_member_licence_no' => $data['member_licence_no'] ?? null,
            'external_member_emergency_contact' => $data['member_emergency_contact'] ?? null,
            'external_cert_level' => $data['cert_level'] ?? null,
            'external_medical_valid_until' => $data['medical_valid_until'] ?? null,
            'external_ref' => $data['external_ref'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'registration_id' => $reg->id,
            'status' => 'pending',
            'message' => 'Registration submitted, awaiting approval by organizing club.',
        ], 201);
    }

    /**
     * DELETE /api/federation/register/{id} — cancel an external registration.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $partner = $this->authenticate($request);
        if (! $partner) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $reg = ExternalRegistration::where('id', $id)->where('partnership_id', $partner->id)->first();
        if (! $reg) {
            return response()->json(['error' => 'Registration not found'], 404);
        }

        $reg->update(['status' => 'cancelled']);

        return response()->json(['status' => 'cancelled']);
    }

    /**
     * GET /api/federation/register/{id} — check registration status.
     */
    public function status(Request $request, int $id): JsonResponse
    {
        $partner = $this->authenticate($request);
        if (! $partner) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $reg = ExternalRegistration::where('id', $id)->where('partnership_id', $partner->id)->first();
        if (! $reg) {
            return response()->json(['error' => 'Registration not found'], 404);
        }

        return response()->json([
            'registration_id' => $reg->id,
            'status' => $reg->status,
            'event_title' => $reg->event->title,
            'event_date' => $reg->event->event_date,
        ]);
    }
}
