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
            $query->inFolder($folder);
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

        return view('admin.library.index', compact('files', 'folder', 'folders', 'search'));
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
        $request->validate(['folder' => 'required|string|max:255']);

        return redirect()->route('admin.library.index', ['folder' => $request->input('folder')]);
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
        $oldPath = storage_path('app/public/library/'.$file->folder.'/'.$file->original_name);
        $newName = $request->name;

        // Keep extension if not provided
        $oldExt = pathinfo($file->original_name, PATHINFO_EXTENSION);
        $newExt = pathinfo($newName, PATHINFO_EXTENSION);
        if (! $newExt && $oldExt) {
            $newName .= '.'.$oldExt;
        }

        $newPath = storage_path('app/public/library/'.$file->folder.'/'.$newName);
        if (file_exists($oldPath)) {
            rename($oldPath, $newPath);
        }
        $file->update(['original_name' => $newName]);

        return back()->with('success', __('File renamed.'));
    }

    public function move(Request $request, LibraryFile $file): RedirectResponse
    {
        $request->validate(['destination' => 'required|string']);
        $dest = $request->destination;

        $oldPath = storage_path('app/public/library/'.$file->folder.'/'.$file->original_name);
        $newDir = storage_path('app/public/library/'.$dest);

        if (! is_dir($newDir)) {
            mkdir($newDir, 0775, true);
        }

        $newPath = $newDir.'/'.$file->original_name;
        if (file_exists($oldPath)) {
            rename($oldPath, $newPath);
        }
        $file->update(['folder' => $dest]);

        return back()->with('success', __('File moved to :folder.', ['folder' => $dest]));
    }
}
