<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\IconHelper;
use App\Http\Requests\StoreLicenceRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileDivingRequest;
use App\Http\Requests\UpdateProfileInfoRequest;
use App\Http\Requests\UpdateProfileLanguageRequest;
use App\Models\Document;
use App\Models\MemberLicence;
use App\Models\MemberStatus;
use App\Models\StatusSet;
use App\Models\User;
use App\Services\MedicalComplianceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

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
        $statusSets = StatusSet::with('statuses:id')->orderBy('name')->get();
        $tab = $request->get('tab', 'info');
        $medicalStatus = app(MedicalComplianceService::class)->getStatus($target);

        $canEdit = $isSelf || $isBureau;
        $tierVault = $isSelf || $isBureau;
        $tierManifest = $tierVault || $isInstructor;

        return view('profile.show', compact('target', 'viewer', 'statuses', 'statusSets', 'tab', 'medicalStatus', 'canEdit', 'tierVault', 'tierManifest'));
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
            // Canonical list from config/countries.php, but tolerate a member's
            // already-stored legacy value so they can still save other fields.
            'nationality' => [
                'nullable', 'string',
                Rule::in(array_values(array_filter(array_merge(
                    (array) config('countries.all'),
                    [$target->detail?->nationality],
                )))),
            ],
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
            $rules['status_set_id'] = 'nullable|exists:status_sets,id';
            $rules['bureau_member'] = 'nullable|boolean';
            $rules['active_instructor'] = 'nullable|boolean';
            $rules['is_lifeguard'] = 'nullable|boolean';
            $rules['adhesion_year'] = 'nullable|integer|min:1900|max:'.date('Y');
            $rules['cotisation_years'] = 'nullable|array';
            $rules['cotisation_years.*'] = 'integer|min:1900|max:'.(date('Y') + 1);
        }

        $validated = $request->validate($rules, [
            'nationality.in' => __('Please choose a nationality from the list.'),
        ]);

        // The chosen status must belong to the assigned set (consistency guard).
        if ($viewer->isBureau() && ! empty($validated['status_set_id']) && ! empty($validated['status_id'])) {
            $set = StatusSet::with('statuses')->find($validated['status_set_id']);
            $offered = $set?->statuses->pluck('id')->all() ?? [];
            if (! in_array((int) $validated['status_id'], $offered, true)) {
                return back()
                    ->withErrors(['status_id' => __('The selected status is not part of the assigned status set.')])
                    ->withInput(['tab' => 'info']);
            }
        }

        if (! $viewer->isBureau() && ($request->has('bureau_member') || $request->has('active_instructor') || $request->has('is_lifeguard'))) {
            abort(403);
        }

        DB::transaction(function () use ($target, $validated, $viewer): void {
            $target->update(array_filter([
                'username' => $validated['username'] ?? null,
            ], fn ($v): bool => $v !== null));

            if (isset($validated['status_id'])) {
                $target->update(['status_id' => $validated['status_id']]);
            }

            if ($viewer->isBureau() && array_key_exists('status_set_id', $validated)) {
                $target->update(['status_set_id' => $validated['status_set_id']]);
            }

            $detailData = collect($validated)->except(['username', 'status_id', 'status_set_id', 'cotisation_years'])->toArray();

            if ($viewer->isBureau()) {
                $detailData['bureau_member'] = $validated['bureau_member'] ?? false;
                $detailData['active_instructor'] = $validated['active_instructor'] ?? false;
                $detailData['is_lifeguard'] = $validated['is_lifeguard'] ?? false;
                $detailData['adhesion_year'] = $validated['adhesion_year'] ?? null;
                if (isset($validated['cotisation_years'])) {
                    $detailData['cotisation_years'] = array_map('strval', $validated['cotisation_years']);
                }
            }

            $target->detail()->updateOrCreate(['user_id' => $target->id], $detailData);
        });

        return back()->with('success', __('Profile updated.'))->withInput(['tab' => 'info']);
    }

    public function updatePrivate(UpdateProfileInfoRequest $request, ?User $user = null): RedirectResponse
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;

        if ($target->id !== $viewer->id && ! $viewer->isBureau()) {
            abort(403);
        }

        $validated = $request->validated();

        $target->detail()->updateOrCreate(['user_id' => $target->id], $validated);

        return back()->with('success', __('Private info updated.'))->withInput(['tab' => 'private']);
    }

    public function storeLicence(StoreLicenceRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $user->id;
        $data['licence_request_pending'] = $request->boolean('licence_request_pending');

        MemberLicence::create($data);

        return back()->with('success', __('Licence added.'))->withInput(['tab' => 'renewal']);
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

    /**
     * The member's real licence card as an image: the scan captured by the
     * Licence Scans intake when there is one, otherwise their own uploaded
     * licence_card PDF rendered to a PNG. Never the CSS recreation — that is
     * the Blade-side fallback for members who have neither.
     */
    public function licenceScanImage(MemberLicence $licence): Response
    {
        $user = auth()->user();
        if ($licence->user_id !== $user->id && ! $user->isBureau()) {
            abort(403);
        }

        $path = $licence->scan_image_path && Storage::disk('local')->exists($licence->scan_image_path)
            ? $licence->scan_image_path
            : $this->renderLicenceCard($licence);

        abort_unless($path !== null, 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Renders page 1 of the member's current licence_card PDF to a PNG, cached
     * on disk so it is rendered once. Null when they have no such document.
     */
    private function renderLicenceCard(MemberLicence $licence): ?string
    {
        $document = Document::query()
            ->where('user_id', $licence->user_id)
            ->where('category', 'licence_card')
            ->where('is_current', true)
            ->latest()
            ->first();

        if (! $document) {
            return null;
        }

        // The local disk root is already storage/app/private/.
        $source = str_starts_with((string) $document->file_path, 'private/')
            ? substr((string) $document->file_path, 8)
            : (string) $document->file_path;

        if (! Storage::disk('local')->exists($source)) {
            return null;
        }

        $cached = 'licence-cards/'.$document->id.'.png';
        if (Storage::disk('local')->exists($cached)) {
            return $cached;
        }

        Storage::disk('local')->makeDirectory('licence-cards');
        exec(sprintf(
            'pdftoppm -png -f 1 -l 1 -scale-to 1000 -singlefile %s %s 2>/dev/null',
            escapeshellarg(Storage::disk('local')->path($source)),
            escapeshellarg(Storage::disk('local')->path('licence-cards/'.$document->id))
        ));

        return Storage::disk('local')->exists($cached) ? $cached : null;
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

    public function updateDiving(UpdateProfileDivingRequest $request, ?User $user = null): RedirectResponse
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

        $validated = $request->validated();

        $validated['other_certifications'] = isset($validated['other_certifications']) && $validated['other_certifications']
            ? array_map('trim', explode(',', $validated['other_certifications'])) : [];
        $validated['training_enrollments'] = isset($validated['training_enrollments']) && $validated['training_enrollments']
            ? array_map('trim', explode(',', $validated['training_enrollments'])) : [];

        $target->detail()->updateOrCreate(['user_id' => $target->id], $validated);

        return back()->with('success', __('Diving info updated.'))->withInput(['tab' => 'diving']);
    }

    public function updateEquipmentSizing(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->id() === $user->id || auth()->user()->isBureau(), 403);

        $data = $request->validate([
            'bcd_size' => 'nullable|string|max:10',
            'tshirt_size' => 'nullable|string|max:10',
            'suit_brand' => 'nullable|string|max:50',
            'suit_size' => 'nullable|string|max:20',
        ]);

        $user->detail()->updateOrCreate(['user_id' => $user->id], $data);

        return back()->with('success', __('Sizing updated.'))->withInput(['tab' => 'equipment']);
    }

    public function updateLanguage(UpdateProfileLanguageRequest $request, ?User $user = null): RedirectResponse
    {
        $viewer = auth()->user();
        $target = $user ?? ($request->target_user_id ? User::findOrFail($request->target_user_id) : $viewer);

        if ($target->id !== $viewer->id && ! $viewer->isBureau()) {
            abort(403);
        }

        $validated = $request->validated();

        $validated['show_icons'] = $request->input('show_icons') === '' ? null : (int) $request->input('show_icons');

        $target->detail()->updateOrCreate(['user_id' => $target->id], $validated);
        // Keep users.preferred_locale (used by outgoing email / newsletters) in
        // step with member_details.preferred_language (used by the UI locale) —
        // the /locale/{locale} switcher already writes both.
        $target->update(['preferred_locale' => $validated['preferred_language']]);
        IconHelper::flush();

        return back()->with('success', __('Language preference updated.'))->withInput(['tab' => 'language']);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        auth()->user()->update(['password' => bcrypt($request->validated('password'))]);

        return back()->with('success', __('Password updated.'))->withInput(['tab' => 'private']);
    }
}
