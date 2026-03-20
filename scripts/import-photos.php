#!/usr/bin/env php
<?php

/**
 * Import photos from a directory into event_photos.
 *
 * Usage: php scripts/import-photos.php <source_dir> <event_id> <uploaded_by_user_id>
 *
 * All imported photos are marked approved=true, gdpr_consent=true, has_faces=false.
 * Quality scoring is done via ImageQualityService.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\EventPhoto;
use App\Services\ImageQualityService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

$sourceDir = $argv[1] ?? null;
$eventId = (int) ($argv[2] ?? 0);
$uploadedBy = (int) ($argv[3] ?? 1);

if (! $sourceDir || ! $eventId) {
    echo "Usage: php scripts/import-photos.php <source_dir> <event_id> <uploaded_by_user_id>\n";
    exit(1);
}

$destDir = "event-photos/{$eventId}";
Storage::disk('public')->makeDirectory($destDir);

$scorer = app(ImageQualityService::class);
$files = glob("{$sourceDir}/*.{jpg,jpeg,png,JPG,JPEG,PNG}", GLOB_BRACE);
$total = count($files);
$imported = 0;
$skipped = 0;

echo "Importing {$total} images into event {$eventId}...\n";

foreach ($files as $i => $file) {
    $basename = basename($file);
    $destPath = "{$destDir}/{$basename}";

    // Skip if already exists
    if (Storage::disk('public')->exists($destPath)) {
        $skipped++;

        continue;
    }

    // Copy file
    $stream = fopen($file, 'r');
    Storage::disk('public')->put($destPath, $stream);
    if (is_resource($stream)) {
        fclose($stream);
    }

    // Score quality
    $realPath = Storage::disk('public')->path($destPath);
    $score = $scorer->score($realPath);

    // Get file size
    $size = filesize($file);

    EventPhoto::create([
        'event_id' => $eventId,
        'uploaded_by' => $uploadedBy,
        'path' => $destPath,
        'quality_score' => $score,
        'has_faces' => false,
        'approved' => true,
        'gdpr_consent' => true,
    ]);

    $imported++;
    if ($imported % 50 === 0) {
        echo "  {$imported}/{$total} (score={$score})...\n";
    }
}

echo "Done: {$imported} imported, {$skipped} skipped (already existed)\n";
