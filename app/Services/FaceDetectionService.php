<?php

/**
 * Face detection via OpenCV (Python script).
 *
 * Detects faces using Haar cascades (frontal + profile). Photos with
 * recognizable faces are flagged so they're excluded from public/anonymous
 * display but still shown to authenticated members.
 *
 * @author ClubCEP.eu
 */

namespace App\Services;

use Illuminate\Support\Facades\Process;

class FaceDetectionService
{
    private string $script;

    public function __construct()
    {
        $this->script = base_path('scripts/detect_faces.py');
    }

    /** Returns true if the image contains recognizable faces. */
    public function hasFaces(string $imagePath): bool
    {
        return $this->detect($imagePath)['has_faces'] ?? false;
    }

    /** Returns face count and detection result. */
    public function detect(string $imagePath): array
    {
        if (! file_exists($imagePath)) {
            return ['faces' => 0, 'has_faces' => false];
        }

        $result = Process::timeout(15)->run(['python3', $this->script, $imagePath]);

        if (! $result->successful()) {
            return ['faces' => 0, 'has_faces' => false, 'error' => $result->errorOutput()];
        }

        return json_decode($result->output(), true) ?? ['faces' => 0, 'has_faces' => false];
    }
}
