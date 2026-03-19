<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $folder = $request->get('folder', '/');
        $files = LibraryFile::inFolder($folder)->orderBy('original_name')->get();

        // Get distinct folders for navigation
        $folders = LibraryFile::selectRaw('DISTINCT folder')->orderBy('folder')->pluck('folder')
            ->flatMap(fn ($f) => collect(explode('/', trim($f, '/')))->filter()->reduce(function ($carry, $part) {
                $carry[] = ($carry->last() ?? '').'/'.$part;

                return $carry;
            }, collect()))
            ->prepend('/')
            ->unique()
            ->sort()
            ->values();

        return view('admin.library.index', compact('files', 'folder', 'folders'));
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

    public function createFolder(Request $request)
    {
        $request->validate(['folder' => 'required|string|max:255']);

        // Folders are implicit — just redirect to the new folder view
        return redirect()->route('admin.library.index', ['folder' => $request->input('folder')]);
    }
}
