<?php

declare(strict_types=1);

/**
 * Member-facing document browser with upload for instructors/bureau.
 *
 * Replaces the old read-only document browser. Files are filtered by the
 * user's role: bureau sees everything, instructors see public+members+instructors,
 * regular members see public+members, guests see public only.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\LibraryFile;
use App\Services\FaceDetectionService;
use App\Services\ImageQualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DocumentBrowserController extends Controller
{
    /** Photo gallery — all approved photos grouped by event. */
    public function gallery(): \Illuminate\Contracts\View\View|RedirectResponse
    {
        $user = auth()->user();
        $query = $user ? EventPhoto::bestForMembers(200) : EventPhoto::bestPublic(200);

        $all = $query->with('event:id,title,event_date')->get()
            ->groupBy(fn ($p) => $p->event_id ?? 0)
            ->map(function ($photos) {
                $event = $photos->first()->event;

                return (object) [
                    'event_id' => $event?->id,
                    'title' => $event?->title ?? __('Other'),
                    'event_date' => $event?->event_date,
                    'latest' => $photos->max('created_at'),
                    'cover' => $photos->first(),
                    'count' => $photos->count(),
                    'photos' => $photos->take(5),
                ];
            })
            ->sortByDesc('latest')
            ->values();

        $page = (int) request('page', 1);
        $perPage = 12;
        $events = new LengthAwarePaginator(
            $all->forPage($page, $perPage), $all->count(), $perPage, $page,
            ['path' => request()->url()]
        );

        return view('gallery', compact('events'));
    }

    public function galleryEvent(Event $event): RedirectResponse|View
    {
        $user = auth()->user();
        $photos = EventPhoto::where('event_id', $event->id)
            ->where('approved', true)->where('gdpr_consent', true)
            ->orderByDesc('quality_score')->get();

        return view('gallery-event', compact('event', 'photos', 'user'));
    }

    public function galleryUpload(Request $request, Event $event): RedirectResponse|View
    {
        $request->validate([
            'photos.*' => 'required|file|max:102400|mimes:jpg,jpeg,png,gif,webp,heic,heif,mp4,mov,avi,webm,zip',
        ]);

        $stored = 0;
        foreach ($request->file('photos', []) as $file) {
            $mime = $file->getMimeType();

            if ($mime === 'application/zip' || $file->getClientOriginalExtension() === 'zip') {
                $tempDir = sys_get_temp_dir().'/gallery_zip_'.uniqid();
                mkdir($tempDir, 0755, true);
                $zip = new \ZipArchive;
                if ($zip->open($file->getRealPath()) === true) {
                    $zip->extractTo($tempDir);
                    $zip->close();
                    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempDir, \FilesystemIterator::SKIP_DOTS));
                    foreach ($files as $f) {
                        if (! $f->isFile() || str_contains($f->getPathname(), '__MACOSX')) {
                            continue;
                        }
                        $fMime = mime_content_type($f->getPathname());
                        if (! str_starts_with($fMime, 'image/') && ! str_starts_with($fMime, 'video/')) {
                            continue;
                        }
                        $ext = pathinfo($f->getFilename(), PATHINFO_EXTENSION) ?: 'jpg';
                        $path = 'event-photos/'.$event->id.'/'.uniqid().'.'.$ext;
                        Storage::disk('public')->put($path, file_get_contents($f->getPathname()));
                        $this->createScoredPhoto($event, $path, $fMime, str_starts_with($fMime, 'image/') ? $f->getPathname() : null);
                        $stored++;
                    }
                }
                // Cleanup
                $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($items as $item) {
                    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
                }
                rmdir($tempDir);
            } else {
                $path = $file->store('event-photos/'.$event->id, 'public');
                $this->createScoredPhoto($event, $path, $mime, str_starts_with($mime, 'image/') ? $file->getRealPath() : null);
                $stored++;
            }
        }

        return back()->with('success', __(':count file(s) uploaded.', ['count' => $stored]));
    }

    private function createScoredPhoto(Event $event, string $path, string $mime, ?string $imagePath): void
    {
        $score = 50;
        $hasFaces = null;

        if ($imagePath) {
            try {
                $score = app(ImageQualityService::class)->score($imagePath);
                $hasFaces = app(FaceDetectionService::class)->hasFaces($imagePath);
            } catch (\Throwable) {
                // Services unavailable — keep defaults
            }
        }

        EventPhoto::create([
            'event_id' => $event->id,
            'uploaded_by' => auth()->id(),
            'path' => $path,
            'mime_type' => $mime,
            'has_faces' => $hasFaces,
            'gdpr_consent' => true,
            'approved' => true,
            'quality_score' => $score,
        ]);
    }

    public function index(Request $request): \Illuminate\Contracts\View\View|RedirectResponse
    {
        $user = auth()->user();
        $folder = $request->get('folder', '/');

        $query = LibraryFile::visibleTo($user);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('original_name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
            $folder = null;
        } else {
            $query->inFolder($folder)->where('original_name', '!=', '.folder');
        }

        $files = $query->orderBy('original_name')->get();

        $folders = LibraryFile::visibleTo($user)
            ->selectRaw('DISTINCT folder')->orderBy('folder')->pluck('folder')
            ->flatMap(fn ($f) => collect(explode('/', trim($f, '/')))->filter()->reduce(function ($carry, $part) {
                $carry[] = ($carry->last() ?? '').'/'.$part;

                return $carry;
            }, collect()))
            ->prepend('/')
            ->unique()
            ->sort()
            ->values();

        $canManage = LibraryFile::canManage($user);

        $subfolders = $folders->filter(function ($f) use ($folder): bool {
            if (! $folder || $f === $folder) {
                return false;
            }
            $parent = dirname($f) === '.' ? '/' : dirname($f);

            return $parent === rtrim($folder, '/') || ($folder === '/' && $parent === '');
        })->values();

        // Personal documents (medical certs, certifications, etc.)
        $myDocuments = $user ? $user->documents()->orderByDesc('created_at')->limit(20)->get() : collect();

        return view('documents.index', compact('files', 'folder', 'folders', 'subfolders', 'canManage', 'search', 'myDocuments'));
    }

    public function upload(Request $request): RedirectResponse
    {
        abort_unless(LibraryFile::canManage(auth()->user()), 403);

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:51200',
            'folder' => 'required|string',
            'visibility' => 'required|in:public,members,instructors,bureau',
            'description' => 'nullable|string|max:500',
        ]);

        foreach ($request->file('files') as $file) {
            $path = $file->store('library', 'local');
            LibraryFile::create([
                'filename' => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'folder' => $request->input('folder'),
                'visibility' => $request->input('visibility'),
                'description' => $request->input('description'),
                'uploaded_by' => auth()->id(),
            ]);
        }

        // Remove folder placeholder now that real files exist
        LibraryFile::where('folder', $request->input('folder'))
            ->where('original_name', '.folder')->delete();

        return back()->with('success', __(':count file(s) uploaded.', ['count' => count($request->file('files'))]));
    }

    public function createFolder(Request $request): BinaryFileResponse|RedirectResponse
    {
        abort_unless(LibraryFile::canManage(auth()->user()), 403);
        $request->validate(['name' => 'required|string|max:100|regex:/^[a-zA-Z0-9_\- ]+$/']);

        $parent = $request->input('parent', '/');
        $newFolder = rtrim($parent, '/').'/'.$request->input('name');

        // Check if folder already has files
        if (LibraryFile::where('folder', $newFolder)->exists()) {
            return redirect()->route('documents.index', ['folder' => $newFolder]);
        }

        // Create a hidden placeholder so the folder appears in the sidebar
        LibraryFile::create([
            'filename' => '.folder',
            'original_name' => '.folder',
            'path' => '',
            'mime_type' => 'inode/directory',
            'size' => 0,
            'folder' => $newFolder,
            'visibility' => 'members',
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->route('documents.index', ['folder' => $newFolder])
            ->with('success', __('Folder created.'));
    }

    public function updateFile(Request $request, LibraryFile $file): BinaryFileResponse|RedirectResponse
    {
        abort_unless(LibraryFile::canManage(auth()->user()), 403);

        $request->validate([
            'visibility' => 'required|in:public,members,instructors,bureau',
            'folder' => 'nullable|string',
            'description' => 'nullable|string|max:500',
        ]);

        $file->update($request->only('visibility', 'folder', 'description'));

        return back()->with('success', __('File updated.'));
    }

    public function destroy(LibraryFile $file): BinaryFileResponse|RedirectResponse
    {
        abort_unless(LibraryFile::canManage(auth()->user()), 403);
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', __('File deleted.'));
    }

    public function download(LibraryFile $file): Response
    {
        // Verify visibility access
        $user = auth()->user();
        $visible = LibraryFile::visibleTo($user)->where('id', $file->id)->exists();
        abort_unless($visible, 403);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    public function thumb(LibraryFile $file): Response
    {
        $user = auth()->user();
        abort_unless(LibraryFile::visibleTo($user)->where('id', $file->id)->exists(), 403);

        if (! $file->hasThumb() || ! Storage::disk('local')->exists($file->path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($file->path));
    }
}
