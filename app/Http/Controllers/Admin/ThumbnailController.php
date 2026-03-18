<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryFile;
use Illuminate\Support\Facades\Storage;

class ThumbnailController extends Controller
{
    public function show(LibraryFile $file)
    {
        $thumbPath = 'thumbnails/' . $file->id . '.jpg';

        if (!Storage::disk('local')->exists($thumbPath)) {
            $generated = $this->generate($file, $thumbPath);
            if (!$generated) {
                abort(404);
            }
        }

        return response(Storage::disk('local')->get($thumbPath), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function generate(LibraryFile $file, string $thumbPath): bool
    {
        $source = Storage::disk('local')->path($file->path);
        $dest = Storage::disk('local')->path($thumbPath);
        Storage::disk('local')->makeDirectory('thumbnails');

        if (str_starts_with($file->mime_type, 'image/')) {
            return $this->imageThumb($source, $dest);
        }

        if ($file->mime_type === 'application/pdf') {
            return $this->pdfThumb($source, $dest);
        }

        return false;
    }

    private function imageThumb(string $source, string $dest): bool
    {
        $img = match (true) {
            str_ends_with($source, '.png') => @imagecreatefrompng($source),
            str_ends_with($source, '.gif') => @imagecreatefromgif($source),
            default => @imagecreatefromjpeg($source),
        };
        if (!$img) return false;

        $w = imagesx($img);
        $h = imagesy($img);
        $size = 200;
        $ratio = min($size / $w, $size / $h);
        $nw = (int) ($w * $ratio);
        $nh = (int) ($h * $ratio);

        $thumb = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagejpeg($thumb, $dest, 75);
        imagedestroy($img);
        imagedestroy($thumb);

        return true;
    }

    private function pdfThumb(string $source, string $dest): bool
    {
        // pdftoppm is faster and more reliable than Ghostscript for thumbnails
        $tmp = $dest . '_tmp';
        $cmd = sprintf(
            'pdftoppm -jpeg -f 1 -l 1 -scale-to 200 %s %s 2>/dev/null',
            escapeshellarg($source),
            escapeshellarg($tmp)
        );
        exec($cmd);

        // pdftoppm appends -1.jpg to the output
        $generated = $tmp . '-1.jpg';
        if (file_exists($generated)) {
            rename($generated, $dest);
            return true;
        }

        return false;
    }
}
