<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(protected BackupService $backup) {}

    public function index(): BinaryFileResponse|RedirectResponse|View
    {
        return view('admin.backups.index', [
            'backups' => $this->backup->list(),
        ]);
    }

    public function create(Request $request): BinaryFileResponse|RedirectResponse|View
    {
        $includeFiles = $request->boolean('include_files', true);

        try {
            $result = $this->backup->create($includeFiles);
            $this->backup->prune((int) config('backup.retention', 4));

            return back()->with('success', __('Backup created: :file (:size)', [
                'file' => $result['filename'],
                'size' => $this->backup->list()[0]['size_human'] ?? '',
            ]));
        } catch (\Throwable $e) {
            return back()->with('error', __('Backup failed: :msg', ['msg' => $e->getMessage()]));
        }
    }

    public function show(string $filename): BinaryFileResponse|RedirectResponse|View
    {
        $filename = basename($filename);
        $path = storage_path("app/backups/{$filename}");
        abort_unless(file_exists($path), 404);

        return view('admin.backups.show', [
            'filename' => $filename,
            'manifest' => $this->backup->readManifest($path),
            'files' => $this->backup->listFiles($path),
            'size' => filesize($path),
            'size_human' => $this->humanSize(filesize($path)),
        ]);
    }

    public function download(string $filename): BinaryFileResponse|RedirectResponse
    {
        $filename = basename($filename);
        $path = storage_path("app/backups/{$filename}");
        abort_unless(file_exists($path), 404);

        return response()->download($path);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $this->backup->delete(basename($filename));

        return back()->with('success', __('Backup deleted.'));
    }

    protected function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
