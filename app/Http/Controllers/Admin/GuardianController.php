<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuardianLink;
use App\Models\ParentalConsent;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class GuardianController extends Controller
{
    public function index(): BinaryFileResponse|RedirectResponse|View
    {
        $minors = User::whereHas('detail', fn ($q) => $q->whereNotNull('date_of_birth')
            ->where('date_of_birth', '>', now()->subYears(18)))
            ->with(['detail', 'guardians', 'parentalConsents.grantedBy'])
            ->get();

        return view('admin.guardians.index', compact('minors'));
    }

    public function linkGuardian(Request $request): BinaryFileResponse|RedirectResponse
    {
        $v = $request->validate([
            'minor_user_id' => 'required|exists:users,id',
            'guardian_user_id' => 'required|exists:users,id|different:minor_user_id',
            'relationship' => 'required|in:parent,legal_guardian',
        ]);

        GuardianLink::firstOrCreate(
            ['guardian_user_id' => $v['guardian_user_id'], 'minor_user_id' => $v['minor_user_id']],
            ['relationship' => $v['relationship']]
        );

        return back()->with('success', __('Guardian linked.'));
    }

    public function unlinkGuardian(GuardianLink $link): BinaryFileResponse|RedirectResponse
    {
        $link->delete();

        return back()->with('success', __('Guardian unlinked.'));
    }

    public function storeConsent(Request $request): BinaryFileResponse|RedirectResponse
    {
        $v = $request->validate([
            'minor_user_id' => 'required|exists:users,id',
            'consent_type' => 'required|in:general,events,photos,medical',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $path = $request->hasFile('document')
            ? $request->file('document')->store('parental-consents', 'local')
            : null;

        ParentalConsent::updateOrCreate(
            ['minor_user_id' => $v['minor_user_id'], 'consent_type' => $v['consent_type']],
            [
                'granted_by' => auth()->id(),
                'granted' => true,
                'granted_at' => now(),
                'revoked_at' => null,
                'document_path' => $path,
            ]
        );

        return back()->with('success', __('Parental consent recorded.'));
    }

    public function revokeConsent(ParentalConsent $consent): BinaryFileResponse|RedirectResponse
    {
        $consent->update(['granted' => false, 'revoked_at' => now()]);

        return back()->with('success', __('Consent revoked.'));
    }

    public function downloadConsent(ParentalConsent $consent): Response
    {
        abort_unless($consent->document_path && Storage::disk('local')->exists($consent->document_path), 404);

        return Storage::disk('local')->download($consent->document_path);
    }
}
