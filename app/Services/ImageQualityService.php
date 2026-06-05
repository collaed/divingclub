<?php

/**
 * Image quality scoring using GD-based analysis.
 *
 * Evaluates sharpness (Laplacian variance), exposure (luminance histogram),
 * color saturation, and contrast to produce a 0-100 quality score.
 * Works on a downscaled copy for speed (~50ms per image).
 *
 * @author ClubCEP.eu
 */

namespace App\Services;

class ImageQualityService
{
    /**
     * Score an image file from 0-100.
     *
     * Breakdown: resolution (15), sharpness (30), exposure (20), saturation (20), contrast (15)
     */
    public function score(string $filePath): int
    {
        $info = @getimagesize($filePath);
        if (! $info) {
            return 0;
        }

        $src = $this->loadImage($filePath, $info[2]);
        if (! $src) {
            return 0;
        }

        // Downscale to ~400px wide for fast analysis
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(400 / $w, 400 / $h, 1);
        $sw = (int) ($w * $scale);
        $sh = (int) ($h * $scale);
        $small = imagecreatetruecolor($sw, $sh);
        imagecopyresampled($small, $src, 0, 0, 0, 0, $sw, $sh, $w, $h);

        // Collect pixel data in one pass
        $luminances = [];
        $saturations = [];
        for ($y = 0; $y < $sh; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Luminance (perceived brightness)
                $luminances[] = 0.299 * $r + 0.587 * $g + 0.114 * $b;

                // Saturation (HSL)
                $max = max($r, $g, $b);
                $min = min($r, $g, $b);
                $l = ($max + $min) / 510; // 0-1
                $saturations[] = ($max === $min) ? 0 : (($max - $min) / (1 - abs(2 * $l - 1)) / 255);
            }
        }

        // 1. Sharpness via Laplacian variance (0-30 pts)
        $sharpness = $this->laplacianVariance($small, $sw, $sh);
        // Typical range: 0-2000+. Score: 500+ is sharp
        $sharpScore = min(30, (int) ($sharpness / 500 * 30));

        // 2. Exposure — penalize over/underexposed (0-20 pts)
        $meanLum = array_sum($luminances) / count($luminances);
        // Ideal mean luminance ~110-150. Penalize extremes.
        $exposureScore = 20 - (int) (abs($meanLum - 130) / 130 * 20);
        $exposureScore = max(0, min(20, $exposureScore));

        // Also penalize if >25% of pixels are clipped (pure black/white)
        $clipped = count(array_filter($luminances, fn (float $l): bool => $l < 10 || $l > 245));
        $clipRatio = $clipped / count($luminances);
        if ($clipRatio > 0.25) {
            $exposureScore = (int) ($exposureScore * (1 - $clipRatio));
        }

        // 3. Saturation — vibrant photos score higher (0-20 pts)
        $meanSat = array_sum($saturations) / count($saturations);
        // Underwater photos: good saturation ~0.3-0.6
        $satScore = min(20, (int) ($meanSat / 0.4 * 20));

        // 4. Contrast — std deviation of luminance (0-15 pts)
        $lumVariance = array_sum(array_map(fn (float $l): float => ($l - $meanLum) ** 2, $luminances)) / count($luminances);
        $lumStdDev = sqrt($lumVariance);
        // Good contrast: stddev 40-80
        $contrastScore = min(15, (int) ($lumStdDev / 60 * 15));

        // 5. Resolution (0-15 pts)
        $megapixels = ($info[0] * $info[1]) / 1_000_000;
        $resScore = min(15, (int) (
            ($megapixels >= 2 ? 10 : $megapixels * 5) +
            ($info[0] >= 1920 ? 3 : 0) +
            ($info[0] > $info[1] ? 2 : 0) // landscape bonus
        ));

        return max(0, min(100, $sharpScore + $exposureScore + $satScore + $contrastScore + $resScore));
    }

    /** Laplacian variance — measures edge energy (sharpness). */
    private function laplacianVariance(\GdImage $img, int $w, int $h): float
    {
        // Convert to grayscale for convolution
        $gray = imagecreatetruecolor($w, $h);
        imagecopy($gray, $img, 0, 0, 0, 0, $w, $h);
        imagefilter($gray, IMG_FILTER_GRAYSCALE);

        // Laplacian kernel
        $kernel = [[0, 1, 0], [1, -4, 1], [0, 1, 0]];
        imageconvolution($gray, $kernel, 1, 128);

        // Measure variance of the convolved image
        $values = [];
        $step = max(1, (int) ($w * $h / 10000)); // sample ~10k pixels for speed
        $i = 0;
        for ($y = 1; $y < $h - 1; $y++) {
            for ($x = 1; $x < $w - 1; $x++) {
                if ($i++ % $step !== 0) {
                    continue;
                }
                $c = imagecolorat($gray, $x, $y) & 0xFF;
                $values[] = $c - 128; // center around 0
            }
        }

        if ($values === []) {
            return 0;
        }

        $mean = array_sum($values) / count($values);

        return array_sum(array_map(fn ($v): float|int => ($v - $mean) ** 2, $values)) / count($values);
    }

    private function loadImage(string $path, int $type): ?\GdImage
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => null,
        } ?: null;
    }
}
