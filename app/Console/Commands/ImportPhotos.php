<?php

/**
 * Import photos from a local directory into an event.
 *
 * Usage: php artisan photos:import {event_id} {directory}
 * Designed for importing Google Photos album exports (Google Takeout).
 *
 * @author ClubCEP.eu
 */

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\User;
use App\Services\FaceDetectionService;
use App\Services\ImageQualityService;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

class ImportPhotos extends Command
{
    protected $signature = 'photos:import
        {event : Event ID}
        {directory : Path to directory containing photos}
        {--user= : Uploader user ID (default: first bureau member)}
        {--approve : Auto-approve all photos}
        {--dry-run : Show what would be imported}';

    protected $description = 'Import photos from a local directory into an event';

    public function handle(ImageQualityService $quality, FaceDetectionService $faces): int
    {
        $event = Event::findOrFail($this->argument('event'));
        $dir = $this->argument('directory');

        if (! is_dir($dir)) {
            $this->error("Directory not found: {$dir}");

            return 1;
        }

        $uploaderId = $this->option('user')
            ?? User::whereHas('roles', fn ($q) => $q->where('name', 'bureau'))->value('id')
            ?? 1;

        $files = collect(glob("{$dir}/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG}", GLOB_BRACE))->sort();
        $this->info("Found {$files->count()} images in {$dir} for event: {$event->title}");

        if ($this->option('dry-run')) {
            $files->each(fn ($f) => $this->line('  '.basename($f)));

            return 0;
        }

        $bar = $this->output->createProgressBar($files->count());
        $imported = 0;

        foreach ($files as $file) {
            $path = Storage::disk('public')->putFile("event-photos/{$event->id}", new File($file));

            $score = $quality->score($file);
            $hasFaces = $faces->hasFaces($file);

            EventPhoto::create([
                'event_id' => $event->id,
                'uploaded_by' => $uploaderId,
                'path' => $path,
                'caption' => null,
                'quality_score' => $score,
                'has_faces' => $hasFaces,
                'approved' => $this->option('approve'),
                'gdpr_consent' => true,
            ]);

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Imported {$imported} photos. Run `photos:scan --faces --force` to re-detect faces if needed.");

        return 0;
    }
}
