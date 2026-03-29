<?php

namespace App\Http\Controllers;

use App\Helpers\IconHelper;
use App\Http\Requests\UpdateProfileLanguageRequest;
use App\Models\MemberLicence;
use App\Models\MemberStatus;
use App\Models\User;
use App\Services\MedicalComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(Request $request, ?User $user = null)
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

    public function updateInfo(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
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

        if ($viewer->id === $target->id || $viewer->isBureauMaster()) {
            $rules['status_id'] = 'nullable|exists:member_statuses,id';
        }

        if ($viewer->isBureauMaster()) {
            $rules['bureau_member'] = 'nullable|boolean';
            $rules['active_instructor'] = 'nullable|boolean';
            $rules['adhesion_year'] = 'nullable|integer|min:1900|max:'.date('Y');
            $rules['cotisation_years'] = 'nullable|array';
            $rules['cotisation_years.*'] = 'integer|min:1900|max:'.(date('Y') + 1);
        }

        $validated = $request->validate($rules);

        if (! $viewer->isBureauMaster()) {
            if ($request->has('bureau_member') || $request->has('active_instructor')) {
                abort(403);
            }
        }

        DB::transaction(function () use ($target, $validated, $viewer) {
            $target->update(array_filter([
                'username' => $validated['username'] ?? null,
            ], fn ($v) => $v !== null));

            if (isset($validated['status_id'])) {
                $target->update(['status_id' => $validated['status_id']]);
            }

            $detailData = collect($validated)->except(['username', 'status_id', 'cotisation_years'])->toArray();

            if ($viewer->isBureauMaster()) {
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

    public function updatePrivate(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
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

    public function updateFederationKey(Request $request, MemberLicence $licence)
    {
        $user = auth()->user();
        if ($licence->user_id !== $user->id && ! $user->isBureauMaster()) {
            abort(403);
        }
        $request->validate(['federation_key' => 'nullable|string|max:20']);
        $licence->update(['federation_key' => strtoupper(trim($request->federation_key))]);

        return back()->with('success', __('Federation key updated.'))->withInput(['tab' => 'renewal']);
    }

    public function updateDiving(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? ($request->target_user_id ? User::findOrFail($request->target_user_id) : $viewer);

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
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

    public function updateLanguage(UpdateProfileLanguageRequest $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? ($request->target_user_id ? User::findOrFail($request->target_user_id) : $viewer);

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $validated = $request->validated();

        $validated['show_icons'] = $validated['show_icons'] === '' || $validated['show_icons'] === null
            ? null : (int) $validated['show_icons'];

        $target->detail()->updateOrCreate(['user_id' => $target->id], $validated);
        IconHelper::flush();

        return back()->with('success', __('Language preference updated.'))->withInput(['tab' => 'language']);
    }
}
