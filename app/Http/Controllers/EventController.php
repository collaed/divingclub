<?php

declare(strict_types=1);

/**
 * Event CRUD, registration, cancellation, and photo management.
 *
 * Handles the full event lifecycle: creation, editing, self-registration,
 * proxy registration (bureau/instructor registering any member), cancellation
 * with audit trail, waiting list auto-promotion, deposit-based payment generation,
 * and GDPR-compliant photo uploads with auto-social-media publishing.
 *
 * @author  ClubCEP.eu
 *
 * @see     \App\Models\Event
 * @see     \App\Models\EventRegistration
 * @see     \App\Services\MedicalComplianceService  — medical cert gate for dive events
 */

namespace App\Http\Controllers;

use App\Helpers\HtmlSanitizer;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UploadEventPhotoRequest;
use App\Models\DiveSite;
use App\Models\EmailLog;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\GdprConsent;
use App\Models\Season;
use App\Models\User;
use App\Services\EventRegistrationService;
use App\Services\FaceDetectionService;
use App\Services\ImageQualityService;
use App\Services\PushNotificationService;
use App\Services\SocialPublishService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    // ─── Calendar Views ────────────────────────────────────────

    public function index(Request $request): View
    {
        $view = $request->get('view', 'month');
        $date = $request->get('date') ? Carbon::parse($request->get('date')) : now();

        $query = Event::where('status', '!=', 'cancelled');

        if ($view === 'month') {
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();
        } elseif ($view === 'week') {
            $start = $date->copy()->startOfWeek();
            $end = $date->copy()->endOfWeek();
        } else {
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();
        }

        $events = $query->where(function ($q) use ($start, $end): void {
            $q->whereBetween('event_date', [$start, $end])
                ->orWhere(function ($q2) use ($start, $end): void {
                    $q2->whereNotNull('end_date')
                        ->where('event_date', '<=', $end)
                        ->where('end_date', '>=', $start);
                });
        })
            ->orderBy('event_date')->orderBy('event_time')
            ->withCount(['confirmedRegistrations as confirmed_count', 'waitingRegistrations as waiting_count'])
            ->get();

        return view('events.index', compact('events', 'view', 'date', 'start', 'end'));
    }

    public function show(Event $event): RedirectResponse|View
    {
        $event->load([
            'registrations.user.detail', 'registrations.user.certificationLevels',
            'registrations.registeredByUser.detail', 'registrations.cancelledByUser.detail',
            'instructor.detail', 'responsible.detail', 'season', 'diveSite',
            'diveGroups.members.user.certificationLevels',
        ]);
        $userReg = auth()->check() ? $event->registrations()->where('user_id', auth()->id())->first() : null;
        $emailHistory = EmailLog::where(function ($q) use ($event): void {
            $q->where('event_id', $event->id)
                ->orWhere('to_email', 'like', "event-{$event->id}@%")
                ->orWhere('alias', 'like', "event-{$event->id}@%");
        })->orderByDesc('created_at')->get();
        $members = auth()->check() ? User::with('detail')->role(['member', 'instructor', 'instructor_apnea', 'bureau_finance', 'bureau_technical', 'bureau_master'])->orderBy('username')->get() : collect();

        return view('events.show', compact('event', 'userReg', 'emailHistory', 'members'));
    }

    // ─── Event CRUD ──────────────────────────────────────────

    public function create(): RedirectResponse|View
    {
        $this->authorizeBureau();
        $seasons = Season::orderByDesc('year')->get();
        $instructors = User::role(['instructor', 'instructor_apnea', 'bureau_master'])->with('detail')->get();
        $diveSites = DiveSite::active()->orderBy('name')->get();
        $locationSuggestions = $this->topLocations();

        return view('events.form', ['event' => new Event, 'seasons' => $seasons, 'instructors' => $instructors, 'diveSites' => $diveSites, 'locationSuggestions' => $locationSuggestions]);
    }

    public function store(StoreEventRequest $request): RedirectResponse|View
    {
        $this->authorizeBureau();
        $data = $request->validated();
        $data['description'] = HtmlSanitizer::clean($data['description'] ?? '');
        $data['created_by'] = auth()->id();
        $data['assistant_ids'] = array_map('intval', array_filter((array) $request->assistant_ids));
        $data['participant_email'] = null; // will be set after creation

        $event = Event::create($data);
        $event->update(['participant_email' => 'event-'.$event->id.'@'.config('club.domain')]);

        // Push notification for non-routine events
        if (! in_array($event->event_type, ['pool', 'theory'])) {
            app(PushNotificationService::class)->sendToAll(
                __('New Event'),
                $event->title.' — '.$event->event_date?->format('d/m/Y'),
                route('events.show', $event)
            );
        }

        return redirect()->route('events.show', $event)->with('success', __('Event created.'));
    }

    public function edit(Event $event): RedirectResponse|View
    {
        $this->authorizeEventEdit($event);
        $seasons = Season::orderByDesc('year')->get();
        $instructors = User::role(['instructor', 'instructor_apnea', 'bureau_master'])->with('detail')->get();
        $diveSites = DiveSite::active()->orderBy('name')->get();
        $locationSuggestions = $this->topLocations();

        return view('events.form', compact('event', 'seasons', 'instructors', 'diveSites', 'locationSuggestions'));
    }

    public function update(StoreEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorizeEventEdit($event);
        $data = $request->validated();
        $data['description'] = HtmlSanitizer::clean($data['description'] ?? '');
        $data['assistant_ids'] = array_map('intval', array_filter((array) $request->assistant_ids));
        $event->update($data);

        return redirect()->route('events.show', $event)->with('success', __('Event updated.'));
    }

    // ─── Registration & Cancellation ──────────────────────────
    // Supports self-registration and proxy registration (any member can be
    // registered by bureau/instructor). Medical compliance is enforced for
    // pool, dive, and training events. Waiting list auto-promotes on cancel.

    public function register(Event $event, Request $request): RedirectResponse
    {
        $actor = auth()->user();
        $service = app(EventRegistrationService::class);
        $nonMemberName = trim((string) $request->input('non_member_name'));

        // Non-member registration (bureau only)
        if ($nonMemberName !== '') {
            $isPrivileged = $actor->isBureau() || $event->instructor_id === $actor->id || in_array($actor->id, $event->assistant_ids ?? []);
            if (! $isPrivileged) {
                return back()->with('error', __('Only bureau members can register non-members.'));
            }

            $result = $service->registerNonMember($event, $nonMemberName, $request->input('comment'), $actor);

            return back()->with($result['success'] ? 'success' : 'error', $result['message']);
        }

        $targetUser = User::findOrFail($request->input('user_id', $actor->id));
        $transitMode = $event->hasTripSettlement() ? $request->input('transit_mode') : null;

        $result = $service->registerMember($event, $targetUser, $actor, $request->input('comment'), $transitMode);

        if ($result['warning']) {
            session()->flash('warning', $result['warning']);
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function cancelRegistration(Event $event, Request $request): RedirectResponse
    {
        $actor = auth()->user();
        $service = app(EventRegistrationService::class);

        if ($request->filled('registration_id')) {
            $reg = $event->registrations()->where('id', $request->input('registration_id'))->whereIn('status', ['confirmed', 'waiting'])->firstOrFail();
        } else {
            $targetUserId = $request->input('user_id', $actor->id);
            $reg = $event->registrations()->where('user_id', $targetUserId)->whereIn('status', ['confirmed', 'waiting'])->firstOrFail();
        }

        $result = $service->cancel($event, $reg, $actor, $request->input('cancel_comment'));

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function updateComment(Event $event, Request $request): RedirectResponse
    {
        $request->validate(['registration_id' => 'required|integer', 'comment' => 'nullable|string|max:500']);
        $reg = $event->registrations()->findOrFail($request->registration_id);
        abort_unless(auth()->user()->isBureau() || $reg->user_id === auth()->id(), 403);
        $reg->update(['comment' => $request->comment]);

        return back()->with('success', __('Comment updated.'));
    }

    public function cancel(Event $event): RedirectResponse
    {
        $this->authorizeBureau();
        $event->update(['status' => 'cancelled']);

        return redirect()->route('events.index')->with('success', __('Event cancelled.'));
    }

    // ─── Photo Gallery (GDPR-gated) ──────────────────────────
    // Only confirmed participants with photo_publication GDPR consent can upload.
    // Photos are auto-scored by quality heuristic and published to social media.

    public function uploadPhoto(UploadEventPhotoRequest $request, Event $event): RedirectResponse
    {

        // GDPR: check photo_publication consent
        $consent = GdprConsent::where('user_id', auth()->id())
            ->where('consent_type', 'photo_publication')->where('granted', true)->exists();
        if (! $consent) {
            return back()->with('error', __('You must grant photo publication consent in Privacy settings before uploading event photos.'));
        }

        $dupes = 0;
        $stored = 0;

        foreach ($request->file('photos', []) as $file) {
            $mime = $file->getMimeType();

            if ($mime === 'application/zip' || $file->getClientOriginalExtension() === 'zip') {
                [$zipStored, $zipDupes] = $this->processZipUpload($file, $event, $request->caption);
                $stored += $zipStored;
                $dupes += $zipDupes;
            } else {
                $result = $this->processMediaFile($file->getRealPath(), $file->getMimeType(), $event, $request->caption, $file);
                if ($result === 'dupe') {
                    $dupes++;
                } elseif ($result === 'stored') {
                    $stored++;
                }
            }
        }

        $msg = __(':count file(s) uploaded.', ['count' => $stored]);
        if ($dupes > 0) {
            $msg .= ' '.__(':count duplicate(s) skipped.', ['count' => $dupes]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Extract a zip and process each image/video inside.
     *
     * @return array{int, int} [stored, dupes]
     */
    private function processZipUpload(UploadedFile $zipFile, Event $event, ?string $caption): array
    {
        $stored = 0;
        $dupes = 0;
        $tempDir = sys_get_temp_dir().'/event_zip_'.uniqid();
        mkdir($tempDir, 0755, true);

        $zip = new \ZipArchive;
        if ($zip->open($zipFile->getRealPath()) === true) {
            $zip->extractTo($tempDir);
            $zip->close();

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempDir, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                // Skip macOS resource fork files
                if (str_contains($file->getPathname(), '__MACOSX')) {
                    continue;
                }

                $mime = mime_content_type($file->getPathname());
                if (! str_starts_with($mime, 'image/') && ! str_starts_with($mime, 'video/')) {
                    continue;
                }

                $result = $this->processMediaFile($file->getPathname(), $mime, $event, $caption);
                if ($result === 'dupe') {
                    $dupes++;
                } elseif ($result === 'stored') {
                    $stored++;
                }
            }
        }

        // Cleanup temp dir
        $this->removeDirectory($tempDir);

        return [$stored, $dupes];
    }

    /**
     * Store a single image or video file as an EventPhoto.
     *
     * @return string 'stored'|'dupe'|'skipped'
     */
    private function processMediaFile(string $realPath, string $mime, Event $event, ?string $caption, ?UploadedFile $uploadedFile = null): string
    {
        $fileHash = hash_file('xxh3', $realPath);

        if (EventPhoto::where('event_id', $event->id)->where('file_hash', $fileHash)->exists()) {
            return 'dupe';
        }

        // Store the file
        if ($uploadedFile) {
            $path = $uploadedFile->store('event-photos/'.$event->id, 'public');
        } else {
            $ext = match (true) {
                str_contains($mime, 'jpeg') => 'jpg',
                str_contains($mime, 'png') => 'png',
                str_contains($mime, 'gif') => 'gif',
                str_contains($mime, 'webp') => 'webp',
                str_contains($mime, 'heic'), str_contains($mime, 'heif') => 'heic',
                str_contains($mime, 'mp4') => 'mp4',
                str_contains($mime, 'quicktime') => 'mov',
                str_contains($mime, 'webm') => 'webm',
                str_contains($mime, 'avi') => 'avi',
                default => pathinfo($realPath, PATHINFO_EXTENSION) ?: 'bin',
            };
            $storagePath = 'event-photos/'.$event->id.'/'.uniqid().'.'.$ext;
            Storage::disk('public')->put($storagePath, file_get_contents($realPath));
            $path = $storagePath;
        }

        $isVideo = str_starts_with($mime, 'video/');
        $score = 50;
        $hasFaces = null;
        $duration = null;

        if ($isVideo) {
            $duration = $this->getVideoDuration(Storage::disk('public')->path($path));
        } else {
            $score = app(ImageQualityService::class)->score($realPath);
            $hasFaces = app(FaceDetectionService::class)->hasFaces($realPath);
        }

        $photo = EventPhoto::create([
            'event_id' => $event->id,
            'uploaded_by' => auth()->id(),
            'path' => $path,
            'mime_type' => $mime,
            'duration' => $duration,
            'caption' => $caption,
            'quality_score' => $score,
            'has_faces' => $hasFaces,
            'gdpr_consent' => true,
            'file_hash' => $fileHash,
        ]);

        if (! $isVideo) {
            app(SocialPublishService::class)->publishToFacebook($photo);
        }

        return 'stored';
    }

    /** Get video duration in seconds via ffprobe, or null if unavailable. */
    private function getVideoDuration(string $path): ?int
    {
        $cmd = sprintf('ffprobe -v quiet -show_entries format=duration -of csv=p=0 %s 2>/dev/null', escapeshellarg($path));
        $output = trim((string) shell_exec($cmd));

        return $output !== '' ? (int) round((float) $output) : null;
    }

    /** Recursively remove a directory. */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    public function deletePhoto(Event $event, EventPhoto $photo): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($photo->event_id === $event->id, 404);
        abort_unless($user->isBureau() || $photo->uploaded_by === $user->id, 403);

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return back()->with('success', __('Photo deleted.'));
    }

    // ─── Authorization & Helpers ──────────────────────────────

    private function authorizeBureau(): void
    {
        abort_unless(auth()->user()->isBureau(), 403);
    }

    /** Bureau can always edit; instructors can edit their own events until permissions expire. */
    private function authorizeEventEdit(Event $event): void
    {
        $user = auth()->user();
        if ($user->isBureau()) {
            return;
        }
        if ($event->instructor_id === $user->id && (! $event->permissions_expire_date || $event->permissions_expire_date->isFuture())) {
            return;
        }
        abort(403);
    }

    private function topLocations(): array
    {
        return Cache::remember('event_top_locations', 3600, fn () => Event::selectRaw('location, count(*) as cnt')
            ->whereNotNull('location')->where('location', '!=', '')
            ->groupBy('location')->orderByDesc('cnt')
            ->pluck('location')->all());
    }
}
