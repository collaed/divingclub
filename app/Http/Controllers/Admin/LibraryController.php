<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $folder = $request->get('folder', '/');

        $query = LibraryFile::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('original_name', $op, "%{$search}%")
                    ->orWhere('description', $op, "%{$search}%");
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

    public function upload(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:51200',
            'folder' => 'required|string',
            'visibility' => 'required|in:public,members,instructors,bureau',
            'description' => 'nullable|string|max:500',
        ]);

        $folder = $request->input('folder', '/');

        foreach ($request->file('files') as $file) {
            $path = $file->store('library', 'local');
            LibraryFile::create([
                'filename' => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'folder' => $folder,
                'visibility' => $request->input('visibility'),
                'description' => $request->input('description'),
                'uploaded_by' => auth()->id(),
            ]);
        }

        return back()->with('success', __('Files uploaded.'));
    }

    public function update(Request $request, LibraryFile $file)
    {
        $request->validate([
            'visibility' => 'required|in:public,members,instructors,bureau',
            'folder' => 'required|string',
            'description' => 'nullable|string|max:500',
        ]);

        $file->update($request->only('visibility', 'folder', 'description'));

        return back()->with('success', __('File updated.'));
    }

    public function destroy(LibraryFile $file)
    {
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', __('File deleted.'));
    }

    public function download(LibraryFile $file)
    {
        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    public function downloadZip(Request $request)
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

    public function createFolder(Request $request)
    {
        $request->validate(['folder' => 'required|string|max:255']);

        return redirect()->route('admin.library.index', ['folder' => $request->input('folder')]);
    }
}
