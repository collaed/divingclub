<?php

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

use App\Models\EventPhoto;
use App\Models\LibraryFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentBrowserController extends Controller
{
    /** Photo gallery — all approved photos grouped by event. */
    public function gallery()
    {
        $user = auth()->user();
        $query = $user ? EventPhoto::bestForMembers(200) : EventPhoto::bestPublic(200);

        $photos = $query->with('event:id,title,event_date')->get()
            ->groupBy(fn ($p) => $p->event?->title ?? __('Other'));

        return view('gallery', compact('photos'));
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $folder = $request->get('folder', '/');

        $files = LibraryFile::visibleTo($user)->inFolder($folder)
            ->orderBy('original_name')->get();

        // Build folder tree from all visible files
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

        return view('documents.index', compact('files', 'folder', 'folders', 'canManage'));
    }

    public function upload(Request $request)
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

        return back()->with('success', __(':count file(s) uploaded.', ['count' => count($request->file('files'))]));
    }

    public function createFolder(Request $request)
    {
        abort_unless(LibraryFile::canManage(auth()->user()), 403);
        $request->validate(['name' => 'required|string|max:100|regex:/^[a-zA-Z0-9_\- ]+$/']);

        $parent = $request->input('parent', '/');
        $newFolder = rtrim($parent, '/').'/'.$request->input('name');

        return redirect()->route('documents.index', ['folder' => $newFolder]);
    }

    public function updateFile(Request $request, LibraryFile $file)
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

    public function destroy(LibraryFile $file)
    {
        abort_unless(LibraryFile::canManage(auth()->user()), 403);
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', __('File deleted.'));
    }

    public function download(LibraryFile $file)
    {
        // Verify visibility access
        $user = auth()->user();
        $visible = LibraryFile::visibleTo($user)->where('id', $file->id)->exists();
        abort_unless($visible, 403);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    public function thumb(LibraryFile $file)
    {
        $user = auth()->user();
        abort_unless(LibraryFile::visibleTo($user)->where('id', $file->id)->exists(), 403);

        if (! $file->hasThumb() || ! Storage::disk('local')->exists($file->path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($file->path));
    }
}
