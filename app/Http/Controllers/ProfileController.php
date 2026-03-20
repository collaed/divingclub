<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\MemberLicence;
use App\Models\MemberStatus;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\MedicalComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function uploadAvatar(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;
        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $path = $request->file('avatar')->store('avatars/'.$target->id, 'public');

        // Delete old avatar
        $old = $target->detail?->avatar_path;
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $target->detail()->updateOrCreate(['user_id' => $target->id], ['avatar_path' => $path]);

        return back()->with('success', __('Photo updated.'));
    }

    public function deleteAvatar(?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;
        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        if ($target->detail?->avatar_path) {
            Storage::disk('public')->delete($target->detail->avatar_path);
            $target->detail->update(['avatar_path' => null]);
        }

        return back()->with('success', __('Photo removed.'));
    }

    public function addCertification(Request $request)
    {
        $request->validate(['certification_level_id' => 'required|exists:certification_levels,id', 'obtained_date' => 'nullable|date']);
        $user = auth()->user();
        $user->certificationLevels()->syncWithoutDetaching([
            $request->certification_level_id => ['obtained_date' => $request->obtained_date, 'display_priority' => 0],
        ]);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Certification added.'));
    }

    public function updateCertification(Request $request, int $certLevel)
    {
        $request->validate(['obtained_date' => 'nullable|date']);
        \DB::table('user_certification_levels')
            ->where('user_id', auth()->id())
            ->where('certification_level_id', $certLevel)
            ->update(['obtained_date' => $request->obtained_date, 'updated_at' => now()]);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Certification updated.'));
    }

    public function setPrimaryCert(int $certLevel)
    {
        $user = auth()->user();
        \DB::table('user_certification_levels')->where('user_id', $user->id)->update(['is_primary' => false]);
        \DB::table('user_certification_levels')->where('user_id', $user->id)->where('certification_level_id', $certLevel)->update(['is_primary' => true, 'display_priority' => \DB::raw('display_priority + 1')]);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Primary certification updated.'));
    }

    public function removeCertification(int $certLevel)
    {
        auth()->user()->certificationLevels()->detach($certLevel);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Certification removed.'));
    }

    public function show(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $target->load(['detail', 'emails', 'licences.federation', 'documents']);
        $statuses = MemberStatus::orderBy('name')->get();
        $tab = $request->get('tab', 'info');
        $medicalStatus = app(MedicalComplianceService::class)->getStatus($target);

        return view('profile.show', compact('target', 'viewer', 'statuses', 'tab', 'medicalStatus'));
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
            'cep_email' => 'nullable|email|max:255',
        ];

        // Only bureau_master can change these
        if ($viewer->isBureauMaster()) {
            $rules['status_id'] = 'nullable|exists:member_statuses,id';
            $rules['bureau_member'] = 'nullable|boolean';
            $rules['active_instructor'] = 'nullable|boolean';
            $rules['adhesion_year'] = 'nullable|integer|min:1900|max:'.date('Y');
            $rules['cotisation_years'] = 'nullable|array';
            $rules['cotisation_years.*'] = 'integer|min:1900|max:'.(date('Y') + 1);
        }

        $validated = $request->validate($rules);

        // Block member from changing restricted fields
        if (! $viewer->isBureauMaster()) {
            if ($request->has('status_id') || $request->has('bureau_member') || $request->has('active_instructor')) {
                abort(403);
            }
        }

        DB::transaction(function () use ($target, $validated, $viewer) {
            $target->update(array_filter([
                'username' => $validated['username'] ?? null,
            ], fn ($v) => $v !== null));

            $detailData = collect($validated)->except(['username', 'status_id', 'cotisation_years'])->toArray();

            if ($viewer->isBureauMaster()) {
                if (isset($validated['status_id'])) {
                    $target->update(['status_id' => $validated['status_id']]);
                }
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

    /** Set the FFESSM InfoLicencié verification key — member (own licence) or bureau. */
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
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        // Instructor bio sub-form
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
            'certification_level' => 'nullable|string|max:50',
            'other_certifications' => 'nullable|string',
            'training_enrollments' => 'nullable|string',
        ]);

        $validated['other_certifications'] = $validated['other_certifications']
            ? array_map('trim', explode(',', $validated['other_certifications'])) : [];
        $validated['training_enrollments'] = $validated['training_enrollments']
            ? array_map('trim', explode(',', $validated['training_enrollments'])) : [];

        $target->detail()->updateOrCreate(['user_id' => $target->id], $validated);

        return back()->with('success', __('Diving info updated.'))->withInput(['tab' => 'diving']);
    }

    public function updateLanguage(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $validated = $request->validate([
            'preferred_language' => 'required|in:en,fr,de,it,es,pt,nl,pl,ro,cs,el,lb',
        ]);

        $target->detail()->updateOrCreate(['user_id' => $target->id], $validated);

        return back()->with('success', __('Language preference updated.'))->withInput(['tab' => 'language']);
    }

    public function uploadDocument(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'category' => 'required|string|in:certification,medical,insurance,other',
            'date_established' => 'nullable|date',
            'cert_type' => 'nullable|string|max:30',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/'.$target->id, 'local');

        $doc = Document::create([
            'user_id' => $target->id,
            'category' => $request->category,
            'cert_type' => $request->cert_type,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'date_established' => $request->date_established,
            'is_current' => true,
        ]);

        // Evaluate medical compliance rules
        if ($request->category === 'medical') {
            app(MedicalComplianceService::class)->evaluateCertificate($doc);
        }

        return back()->with('success', __('Document uploaded.'));
    }

    public function downloadDocument(Document $document)
    {
        $viewer = auth()->user();
        if ($document->user_id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }

    // Email management
    public function addEmail(Request $request)
    {
        $user = auth()->user();

        if ($user->emails()->count() >= 5) {
            return back()->with('error', __('Maximum of 5 email addresses allowed.'));
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:user_emails,email',
            'label' => 'nullable|string|max:50',
        ]);

        UserEmail::create([
            'user_id' => $user->id,
            'email' => $validated['email'],
            'is_primary' => false,
            'is_verified' => false,
            'label' => $validated['label'],
            'verification_token' => Str::random(64),
            'verification_sent_at' => now(),
        ]);

        // TODO: Send verification email in Phase 6

        return back()->with('success', __('Email added. Please verify it.'));
    }

    public function setPrimaryEmail(UserEmail $email)
    {
        $user = auth()->user();
        if ($email->user_id !== $user->id && ! $user->isBureauMaster()) {
            abort(403);
        }
        if (! $email->is_verified) {
            return back()->with('error', __('Only verified emails can be set as primary.'));
        }

        DB::transaction(function () use ($email) {
            UserEmail::where('user_id', $email->user_id)->update(['is_primary' => false]);
            $email->update(['is_primary' => true]);
            User::where('id', $email->user_id)->update(['primary_email' => $email->email]);
        });

        return back()->with('success', __('Primary email updated.'));
    }

    public function deleteEmail(UserEmail $email)
    {
        $user = auth()->user();
        if ($email->user_id !== $user->id && ! $user->isBureauMaster()) {
            abort(403);
        }
        if ($email->is_primary) {
            return back()->with('error', __('Cannot delete primary email. Set another as primary first.'));
        }

        $email->delete();

        return back()->with('success', __('Email removed.'));
    }

    public function verifyCertificate(Request $request, Document $document)
    {
        abort_unless(auth()->user()->isBureauMaster(), 403);

        $data = ['is_verified' => true, 'verified_by' => auth()->id(), 'verified_at' => now()];

        if ($request->filled('date_established')) {
            $data['date_established'] = $request->date('date_established');
        }

        if ($request->filled('cert_type')) {
            $data['cert_type'] = $request->input('cert_type');
        }

        $document->update($data);

        if ($document->category === 'medical') {
            app(MedicalComplianceService::class)->evaluateCertificate($document);
        }

        return back()->with('success', __('Certificate verified.'));
    }
}
