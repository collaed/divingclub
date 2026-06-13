<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\HttpFoundation\Response;

class ThumbnailController extends Controller
{
    public function show(LibraryFile $file): Response
    {
        $thumbPath = 'thumbnails/'.$file->id.'.jpg';

        if (! Storage::disk('local')->exists($thumbPath)) {
            $generated = $this->generate($file, $thumbPath);
            if (! $generated) {
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
        Storage::disk('local')->makeDirectory('thumbnails');
        $dest = Storage::disk('local')->path($thumbPath);

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
        try {
            Image::decode(file_get_contents($source))
                ->scaleDown(200, 200)
                ->encodeUsingMediaType('image/jpeg', quality: 75)
                ->save($dest);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function pdfThumb(string $source, string $dest): bool
    {
        $tmp = $dest.'_tmp';
        exec(sprintf(
            'pdftoppm -jpeg -f 1 -l 1 -scale-to 200 %s %s 2>/dev/null',
            escapeshellarg($source),
            escapeshellarg($tmp)
        ));

        $generated = $tmp.'-1.jpg';
        if (file_exists($generated)) {
            rename($generated, $dest);

            return true;
        }

        return false;
    }
}
