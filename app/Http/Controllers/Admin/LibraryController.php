<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryFile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class LibraryController extends Controller
{
    public function index(Request $request): RedirectResponse|View
    {
        $folder = $request->get('folder', '/');

        $query = LibraryFile::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('original_name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
            $folder = null; // search across all folders
        } else {
            $query->inFolder($folder)->where('original_name', '!=', '.folder');
        }

        $files = $query->orderBy('original_name')->get();

        $folders = LibraryFile::selectRaw('DISTINCT folder')->orderBy('folder')->pluck('folder')
            ->flatMap(fn ($f) => collect(explode('/', trim($f, '/')))->filter()->reduce(function ($carry, $part) {
                $carry[] = ($carry->last() ?? '').'/'.$part;

                return $carry;
            }, collect()))
            ->prepend('/')
            ->unique()
            ->sort()
            ->values();

        // Per-folder counts for the sidebar badges: how many files live
        // directly in it, and how many direct subfolders it has.
        $fileCounts = LibraryFile::where('original_name', '!=', '.folder')
            ->selectRaw('folder, count(*) as cnt')->groupBy('folder')->pluck('cnt', 'folder');
        $subfolderCounts = $folders->reject(fn (string $f): bool => $f === '/')->countBy(fn (string $f): string => dirname($f));

        return view('admin.library.index', compact('files', 'folder', 'folders', 'search', 'fileCounts', 'subfolderCounts'));
    }

    public function upload(Request $request): BinaryFileResponse|RedirectResponse
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:51200',
            'folder' => 'required|string',
            'visibility' => 'required|in:public,members,instructors,bureau',
            'description' => 'nullable|string|max:500',
        ]);

        $folder = $request->input('folder', '/');

        $stored = [];
        foreach ($request->file('files') as $file) {
            $origName = $file->getClientOriginalName();
            $path = $file->store('library', 'local');
            LibraryFile::create([
                'filename' => basename($path),
                'original_name' => $origName,
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'folder' => $folder,
                'visibility' => $request->input('visibility'),
                'description' => $request->input('description'),
                'uploaded_by' => auth()->id(),
            ]);
            $stored[$origName] = $path;
        }

        // Remove the folder placeholder now that a real file exists here.
        LibraryFile::where('folder', $folder)->where('original_name', '.folder')->delete();

        // Auto-copy to incoming/ for member matching
        if (stripos($folder, 'incoming') !== false) {
            $incomingDir = storage_path('app/incoming');
            if (! is_dir($incomingDir)) {
                mkdir($incomingDir, 0755, true);
            }
            foreach ($stored as $origName => $path) {
                $src = Storage::disk('local')->path($path);
                if (file_exists($src)) {
                    copy($src, $incomingDir.'/'.$origName);
                }
            }
        }

        return back()->with('success', __('Files uploaded.'));
    }

    public function update(Request $request, LibraryFile $file): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        $request->validate([
            'visibility' => 'required|in:public,members,instructors,bureau',
            'folder' => 'required|string',
            'description' => 'nullable|string|max:500',
        ]);

        $file->update($request->only('visibility', 'folder', 'description'));

        return back()->with('success', __('File updated.'));
    }

    public function destroy(LibraryFile $file): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', __('File deleted.'));
    }

    public function download(LibraryFile $file): Response
    {
        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    public function downloadZip(Request $request): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        $ids = explode(',', $request->input('ids', ''));
        $files = LibraryFile::whereIn('id', $ids)->get();

        if ($files->isEmpty()) {
            return back()->with('error', __('No files selected.'));
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'lib_').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        foreach ($files as $f) {
            $diskPath = Storage::disk('local')->path($f->path);
            if (file_exists($diskPath)) {
                $zip->addFile($diskPath, $f->original_name);
            }
        }
        $zip->close();

        return response()->download($zipPath, 'library-export.zip')->deleteFileAfterSend();
    }

    public function createFolder(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['name' => 'required|string|max:100|regex:/^[a-zA-Z0-9_\- ]+$/']);

        $parent = $request->input('parent', '/');
        $newFolder = rtrim($parent, '/').'/'.$request->input('name');

        if (LibraryFile::where('folder', $newFolder)->exists()) {
            return redirect()->route('admin.library.index', ['folder' => $newFolder]);
        }

        // A folder only exists as a byproduct of files having that folder
        // value — this hidden placeholder is what makes an otherwise-empty
        // folder show up in the tree at all. upload() deletes it once a
        // real file lands in the same folder.
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

        return redirect()->route('admin.library.index', ['folder' => $newFolder])
            ->with('success', __('Folder created.'));
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $files = LibraryFile::whereIn('id', $request->ids)->get();
        foreach ($files as $file) {
            Storage::disk('local')->delete($file->path);
            $file->delete();
        }

        return response()->json(['deleted' => $files->count()]);
    }

    public function rename(Request $request, LibraryFile $file): RedirectResponse
    {
        $request->validate(['name' => 'required|string|max:255']);
        $newName = $request->name;

        // Keep extension if not provided
        $oldExt = pathinfo($file->original_name, PATHINFO_EXTENSION);
        $newExt = pathinfo($newName, PATHINFO_EXTENSION);
        if (! $newExt && $oldExt) {
            $newName .= '.'.$oldExt;
        }

        // Only original_name (the display name) changes — the stored file
        // itself lives at $file->path under a random name unrelated to it
        // (see upload()'s $file->store('library', 'local')), so there is no
        // physical file to rename.
        $file->update(['original_name' => $newName]);

        return back()->with('success', __('File renamed.'));
    }

    public function move(Request $request, LibraryFile $file): RedirectResponse
    {
        $request->validate(['destination' => 'required|string']);

        // folder is a virtual grouping column, not a real directory the
        // file lives in (see rename() above) — moving it is a DB-only op.
        $file->update(['folder' => $request->destination]);

        return back()->with('success', __('File moved to :folder.', ['folder' => $request->destination]));
    }

    public function copy(LibraryFile $file): RedirectResponse
    {
        if (! Storage::disk('local')->exists($file->path)) {
            return back()->with('error', __('Original file is missing on disk.'));
        }

        $ext = pathinfo($file->path, PATHINFO_EXTENSION);
        $newPath = 'library/'.Str::random(40).($ext ? '.'.$ext : '');
        Storage::disk('local')->copy($file->path, $newPath);

        LibraryFile::create([
            'filename' => basename($newPath),
            'original_name' => $file->original_name,
            'path' => $newPath,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'folder' => $file->folder,
            'visibility' => $file->visibility,
            'description' => $file->description,
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', __('File copied.'));
    }

    public function renameFolder(Request $request): RedirectResponse
    {
        $request->validate([
            'path' => 'required|string',
            'name' => 'required|string|max:100|regex:/^[a-zA-Z0-9_\- ]+$/',
        ]);

        $path = rtrim($request->input('path'), '/');
        // rtrim, not a dirname === '.' check: dirname('/One') is '/', and
        // naively appending '/'.$name to that doubles the slash ('//Two').
        $newPath = rtrim(dirname($path), '/').'/'.$request->input('name');

        if ($path === '/' || $newPath === $path) {
            return back()->with('error', __('Cannot rename this folder.'));
        }
        if (LibraryFile::where('folder', $newPath)->orWhere('folder', 'like', $newPath.'/%')->exists()) {
            return back()->with('error', __('A folder with that name already exists here.'));
        }

        // folder is a plain DB column, not a real directory — renaming a
        // folder means rewriting that column's prefix on every file under it.
        LibraryFile::where('folder', $path)->orWhere('folder', 'like', $path.'/%')
            ->get()
            ->each(fn (LibraryFile $f) => $f->update(['folder' => $newPath.mb_substr($f->folder, mb_strlen($path))]));

        return redirect()->route('admin.library.index', ['folder' => $newPath])
            ->with('success', __('Folder renamed.'));
    }

    public function deleteFolder(Request $request): RedirectResponse
    {
        $request->validate(['path' => 'required|string']);
        $path = rtrim($request->input('path'), '/');

        abort_if($path === '/' || $path === '', 400, __('Cannot delete the root folder.'));

        $files = LibraryFile::where('folder', $path)->orWhere('folder', 'like', $path.'/%')->get();
        foreach ($files as $file) {
            if ($file->path !== '') {
                Storage::disk('local')->delete($file->path);
            }
            $file->delete();
        }

        return redirect()->route('admin.library.index', ['folder' => dirname($path) === '.' ? '/' : dirname($path)])
            ->with('success', __(':count file(s) deleted with the folder.', ['count' => $files->count()]));
    }
}
