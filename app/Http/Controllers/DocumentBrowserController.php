<?php

namespace App\Http\Controllers;

use App\Models\LibraryFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentBrowserController extends Controller
{
    public function index(Request $request)
    {
        $folder = $request->get('folder', '/');
        $files = LibraryFile::public()->inFolder($folder)->orderBy('original_name')->get();

        $folders = LibraryFile::public()
            ->selectRaw('DISTINCT folder')->orderBy('folder')->pluck('folder')
            ->prepend('/')
            ->unique()
            ->sort()
            ->values();

        return view('documents.index', compact('files', 'folder', 'folders'));
    }

    public function download(LibraryFile $file)
    {
        abort_unless($file->is_public, 403);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    public function thumb(LibraryFile $file)
    {
        abort_unless($file->is_public, 403);

        return app(\App\Http\Controllers\Admin\ThumbnailController::class)->show($file);
    }
}
