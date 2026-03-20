<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\EventPhoto;
use App\Services\ImageQualityService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

$eventId = 2442;
$uploadedBy = 1;
$sourceDir = '/tmp/import-oman';
$destDir = "event-photos/{$eventId}";

Storage::disk('public')->makeDirectory($destDir);
$scorer = app(ImageQualityService::class);

// Load selected files
$uwFiles = file('/tmp/oman-uw-pick.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$sfFiles = file('/tmp/oman-sf-pick.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$allFiles = array_merge(
    array_map(fn ($f) => ['file' => $f, 'faces' => false], $uwFiles),
    array_map(fn ($f) => ['file' => $f, 'faces' => true], $sfFiles)
);

$total = count($allFiles);
$imported = 0;

echo "Importing {$total} selected images into event {$eventId}...\n";

foreach ($allFiles as $item) {
    $basename = $item['file'];
    $srcPath = "{$sourceDir}/{$basename}";
    $destPath = "{$destDir}/{$basename}";

    if (! file_exists($srcPath)) {
        continue;
    }
    if (Storage::disk('public')->exists($destPath)) {
        $imported++;

        continue;
    }

    $stream = fopen($srcPath, 'r');
    Storage::disk('public')->put($destPath, $stream);
    if (is_resource($stream)) {
        fclose($stream);
    }

    $realPath = Storage::disk('public')->path($destPath);
    $score = $scorer->score($realPath);

    EventPhoto::create([
        'event_id' => $eventId,
        'uploaded_by' => $uploadedBy,
        'path' => $destPath,
        'quality_score' => $score,
        'has_faces' => $item['faces'],
        'approved' => true,
        'gdpr_consent' => true,
    ]);

    $imported++;
    if ($imported % 50 === 0) {
        echo "  {$imported}/{$total}...\n";
    }
}

echo "Done: {$imported} imported\n";

// Summary
$uw = EventPhoto::where('event_id', $eventId)->where('has_faces', false)->count();
$sf = EventPhoto::where('event_id', $eventId)->where('has_faces', true)->count();
echo "Event {$eventId}: {$uw} underwater + {$sf} surface/faces = ".($uw + $sf)." total\n";
