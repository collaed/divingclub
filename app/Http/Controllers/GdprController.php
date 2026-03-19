<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GdprConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GdprController extends Controller
{
    public function consents()
    {
        $consents = auth()->user()->gdprConsents()->get()->keyBy('consent_type');
        return view('gdpr.consents', compact('consents'));
    }

    public function updateConsent(Request $request)
    {
        $request->validate(['consent_type' => 'required|in:data_processing,marketing,photo_publication']);
        $granted = $request->boolean('granted');

        GdprConsent::updateOrCreate(
            ['user_id' => auth()->id(), 'consent_type' => $request->consent_type],
            [
                'granted' => $granted,
                'granted_at' => $granted ? now() : null,
                'revoked_at' => !$granted ? now() : null,
            ]
        );

        return back()->with('success', __('Consent updated.'));
    }

    public function exportData()
    {
        $user = auth()->user();
        $user->load(['detail', 'emails', 'licences', 'documents', 'gdprConsents']);

        $data = [
            'user' => $user->only(['id', 'username', 'primary_email', 'created_at']),
            'detail' => $user->detail?->toArray(),
            'emails' => $user->emails->toArray(),
            'licences' => $user->licences->toArray(),
            'documents' => $user->documents->map(fn($d) => $d->only(['category', 'original_filename', 'date_established', 'created_at']))->toArray(),
            'consents' => $user->gdprConsents->toArray(),
            'exported_at' => now()->toIso8601String(),
        ];

        $filename = "gdpr-export-{$user->id}-" . now()->format('Ymd') . '.json';
        return response()->json($data)->header('Content-Disposition', "attachment; filename={$filename}");
    }

    public function requestErasure()
    {
        return view('gdpr.erasure-confirm');
    }

    public function confirmErasure(Request $request)
    {
        $request->validate([
            'confirm' => 'required|accepted',
            'password' => 'required|current_password',
        ]);
        $user = auth()->user();

        // Delete documents
        foreach ($user->documents as $doc) {
            Storage::disk('local')->delete($doc->file_path);
        }
        $user->documents()->delete();

        // Delete avatar
        if ($user->detail?->avatar_path) {
            Storage::disk('public')->delete($user->detail->avatar_path);
        }

        // Anonymize
        $user->detail?->update([
            'first_name' => 'ERASED', 'last_name' => 'ERASED', 'birth_name' => null,
            'phone_private' => null, 'phone_office' => null, 'phone_mobile' => null,
            'date_of_birth' => null, 'place_of_birth' => null,
            'address_line1' => null, 'address_line2' => null, 'city' => null, 'postal_code' => null,
            'emergency_contact_name' => null, 'emergency_contact_phone' => null,
            'avatar_path' => null,
        ]);

        $user->update(['primary_email' => "erased-{$user->id}@erased.local", 'password' => null, 'username' => null]);
        $user->emails()->delete();
        $user->socialAccounts()->delete();

        AuditLog::create([
            'user_id' => $user->id, 'action' => 'gdpr_erasure',
            'model_type' => 'App\\Models\\User', 'model_id' => $user->id,
            'new_values' => ['erased_at' => now()->toIso8601String()],
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);

        auth()->logout();
        return redirect('/')->with('success', __('Your data has been erased.'));
    }
}
