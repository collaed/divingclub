<?php

namespace App\Http\Controllers;

use App\Helpers\IconHelper;
use App\Http\Requests\UpdateProfileLanguageRequest;
use App\Models\MemberLicence;
use App\Models\MemberStatus;
use App\Models\User;
use App\Services\MedicalComplianceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ProfileController extends Controller
{
    public function show(Request $request, ?User $user = null): View
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;
        $isSelf = $viewer->id === $target->id;
        $isBureau = $viewer->isBureau();
        $isInstructor = $viewer->hasAnyRole(['instructor', 'assistant']);

        if (! $isSelf && ! $isBureau && ! auth()->check()) {
            abort(403);
        }

        $target->load(['detail', 'emails', 'licences.federation', 'documents']);
        $statuses = MemberStatus::orderBy('name')->get();
        $tab = $request->get('tab', 'info');
        $medicalStatus = app(MedicalComplianceService::class)->getStatus($target);

        $canEdit = $isSelf || $isBureau;
        $tierVault = $isSelf || $isBureau;
        $tierManifest = $tierVault || $isInstructor;

        return view('profile.show', compact('target', 'viewer', 'statuses', 'tab', 'medicalStatus', 'canEdit', 'tierVault', 'tierManifest'));
    }

    public function updateInfo(Request $request, ?User $user = null): RedirectResponse
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureau()) {
            abort(403);
        }

        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,'.$target->id,
            'nationality' => 'nullable|string|max:100',
            'phone_private' => 'nullable|string|max:50',
            'phone_office' => 'nullable|string|max:50',
            'phone_mobile' => 'nullable|string|max:50',
            'sex' => 'required|in:M,F,X',
            'club_email' => 'nullable|email|max:255',
        ];

        if ($viewer->id === $target->id || $viewer->isBureau()) {
            $rules['status_id'] = 'nullable|exists:member_statuses,id';
        }

        if ($viewer->isBureau()) {
            $rules['bureau_member'] = 'nullable|boolean';
            $rules['active_instructor'] = 'nullable|boolean';
            $rules['adhesion_year'] = 'nullable|integer|min:1900|max:'.date('Y');
            $rules['cotisation_years'] = 'nullable|array';
            $rules['cotisation_years.*'] = 'integer|min:1900|max:'.(date('Y') + 1);
        }

        $validated = $request->validate($rules);

        if (! $viewer->isBureau() && ($request->has('bureau_member') || $request->has('active_instructor'))) {
            abort(403);
        }

        DB::transaction(function () use ($target, $validated, $viewer): void {
            $target->update(array_filter([
                'username' => $validated['username'] ?? null,
            ], fn ($v): bool => $v !== null));

            if (isset($validated['status_id'])) {
                $target->update(['status_id' => $validated['status_id']]);
            }

            $detailData = collect($validated)->except(['username', 'status_id', 'cotisation_years'])->toArray();

            if ($viewer->isBureau()) {
                $detailData['bureau_member'] = $validated['bureau_member'] ?? false;
                $detailData['active_instructor'] = $validated['active_instructor'] ?? false;
                $detailData['adhesion_year'] = $validated['adhesion_year'] ?? null;
                if (isset($validated['cotisation_years'])) {
                    $detailData['cotisation_years'] = array_map('strval', $validated['cotisation_years']);
                }
            }

            $target->detail()->updateOrCreate(['user_id' => $target->id], $detailData);
        });

        return back()->with('success', __('Profile updated.'))->withInput(['tab' => 'info']);
    }

    public function updatePrivate(Request $request, ?User $user = null): RedirectResponse
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureau()) {
            abort(403);
        }

        $validated = $request->validate([
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string|max:255',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:34',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'brevet_date' => 'nullable|date',
        ]);

        $target->detail()->updateOrCreate(['user_id' => $target->id], $validated);

        return back()->with('success', __('Private info updated.'))->withInput(['tab' => 'private']);
    }

    public function updateLicence(Request $request, MemberLicence $licence): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage members'), 403);

        $licence->update($request->validate([
            'licence_number' => 'nullable|string|max:50',
            'licence_request_date' => 'nullable|date',
            'licence_request_pending' => 'boolean',
            'season' => 'nullable|string|max:20',
            'insurance_type' => 'nullable|string|max:50',
        ]));

        return back()->with('success', __('Licence updated.'))->withInput(['tab' => 'renewal']);
    }

    public function updateFederationKey(Request $request, MemberLicence $licence): RedirectResponse
    {
        $user = auth()->user();
        if ($licence->user_id !== $user->id && ! $user->isBureau()) {
            abort(403);
        }
        $request->validate(['federation_key' => 'nullable|string|max:20']);
        $key = strtoupper(trim($request->federation_key));
        $licence->update(['federation_key' => $key]);

        // Verify against FFESSM if applicable
        $warning = null;
        if ($key && $licence->federation?->acronym === 'FFESSM') {
            $number = preg_replace('/^[A-Z]-\d{2}-/', '', $licence->licence_number);
            try {
                $html = Http::timeout(5)->get("https://infolicencie.ffessm.fr/Home/InfoLicence?number={$number}&key={$key}")->body();
                $memberName = $licence->user->detail?->last_name ?? '';
                if (stripos($html, $memberName) !== false) {
                    $warning = null; // Name found — all good
                } elseif (stripos($html, 'introuvable') !== false || strlen($html) < 500) {
                    $warning = __('Warning: FFESSM returned no result for this key. Please verify.');
                } else {
                    $warning = __('Warning: the FFESSM page does not mention :name. Please verify the key.', ['name' => $memberName]);
                }
            } catch (\Throwable) {
                // FFESSM unreachable — skip validation
            }
        }

        $msg = __('Federation key updated.');
        if ($warning) {
            return back()->with('warning', $warning)->with('success', $msg)->withInput(['tab' => 'renewal']);
        }

        return back()->with('success', $msg)->withInput(['tab' => 'renewal']);
    }

    public function updateDiving(Request $request, ?User $user = null): RedirectResponse
    {
        $viewer = auth()->user();
        $target = $user ?? ($request->target_user_id ? User::findOrFail($request->target_user_id) : $viewer);

        if ($target->id !== $viewer->id && ! $viewer->isBureau()) {
            abort(403);
        }

        if ($request->input('tab') === 'instructor_bio') {
            $request->validate([
                'instructor_bio' => 'nullable|string|max:2000',
                'instructor_specialties' => 'nullable|string|max:1000',
                'instructor_motivation' => 'nullable|string|max:1000',
                'show_on_public_site' => 'boolean',
            ]);
            $target->detail()->updateOrCreate(['user_id' => $target->id], $request->only('instructor_bio', 'instructor_specialties', 'instructor_motivation', 'show_on_public_site'));

            return back()->with('success', __('Instructor profile updated.'))->withInput(['tab' => 'diving']);
        }

        $validated = $request->validate([
            'dive_count' => 'nullable|integer|min:0',
            'total_dives' => 'nullable|integer|min:0',
            'last_dive_date' => 'nullable|date',
            'air_consumption' => 'nullable|numeric|min:0|max:1',
            'ease_level' => 'nullable|numeric|min:0|max:1',
            'primary_intent' => 'nullable|string|in:exploration,photography,training,deep,wreck,night,drift',
            'is_photographer' => 'nullable|boolean',
            'certification_level' => 'nullable|string|max:50',
            'apnea_level' => 'nullable|string|max:50',
            'other_certifications' => 'nullable|string',
            'training_enrollments' => 'nullable|string',
        ]);

        $validated['other_certifications'] = isset($validated['other_certifications']) && $validated['other_certifications']
            ? array_map('trim', explode(',', $validated['other_certifications'])) : [];
        $validated['training_enrollments'] = isset($validated['training_enrollments']) && $validated['training_enrollments']
            ? array_map('trim', explode(',', $validated['training_enrollments'])) : [];

        $target->detail()->updateOrCreate(['user_id' => $target->id], $validated);

        return back()->with('success', __('Diving info updated.'))->withInput(['tab' => 'diving']);
    }

    public function updateLanguage(UpdateProfileLanguageRequest $request, ?User $user = null): RedirectResponse
    {
        $viewer = auth()->user();
        $target = $user ?? ($request->target_user_id ? User::findOrFail($request->target_user_id) : $viewer);

        if ($target->id !== $viewer->id && ! $viewer->isBureau()) {
            abort(403);
        }

        $validated = $request->validated();

        $validated['show_icons'] = $validated['show_icons'] === '' || $validated['show_icons'] === null
            ? null : (int) $validated['show_icons'];

        $target->detail()->updateOrCreate(['user_id' => $target->id], $validated);
        IconHelper::flush();

        return back()->with('success', __('Language preference updated.'))->withInput(['tab' => 'language']);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ]);

        auth()->user()->update(['password' => bcrypt($request->password)]);

        return back()->with('success', __('Password updated.'))->withInput(['tab' => 'private']);
    }
}
