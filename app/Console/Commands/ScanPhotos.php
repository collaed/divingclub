<?php

declare(strict_types=1);

/**
 * Scan existing event photos for faces and rescore quality.
 *
 * @author ClubCEP.eu
 */

namespace App\Console\Commands;

use App\Models\EventPhoto;
use App\Services\FaceDetectionService;
use App\Services\ImageQualityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ScanPhotos extends Command
{
    protected $signature = 'photos:scan
        {--faces : Detect faces}
        {--quality : Rescore quality}
        {--force : Rescan all, not just unscanned}';

    protected $description = 'Scan event photos for faces and/or rescore quality';

    public function handle(FaceDetectionService $faceService, ImageQualityService $qualityService): int
    {
        $doFaces = $this->option('faces') || ! $this->option('quality');
        $doQuality = $this->option('quality') || ! $this->option('faces');
        $force = $this->option('force');

        $query = EventPhoto::query();
        if (! $force) {
            $query->where(function ($q) use ($doFaces, $doQuality): void {
                if ($doFaces) {
                    $q->orWhereNull('has_faces');
                }
                if ($doQuality) {
                    $q->orWhereNull('quality_score')->orWhere('quality_score', 0);
                }
            });
        }

        $photos = $query->get();
        $this->info("Scanning {$photos->count()} photos…");

        $bar = $this->output->createProgressBar($photos->count());
        $facesFound = 0;

        foreach ($photos as $photo) {
            $path = Storage::disk('public')->path($photo->path);
            if (! file_exists($path)) {
                $bar->advance();

                continue;
            }

            $updates = [];
            if ($doQuality) {
                $updates['quality_score'] = $qualityService->score($path);
            }
            if ($doFaces) {
                $updates['has_faces'] = $faceService->hasFaces($path);
                if ($updates['has_faces']) {
                    $facesFound++;
                }
            }

            $photo->update($updates);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($doFaces) {
            $this->info("Faces detected in {$facesFound}/{$photos->count()} photos.");
        }
        $this->info('Done.');

        return 0;
    }
}
