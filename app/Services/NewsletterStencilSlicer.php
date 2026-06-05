<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Slices a newsletter stencil image into 5 components.
 *
 * Expected stencil proportions (based on 1200×1860 reference):
 *   Header:    0,0       → 1200,400    (full width, top)
 *   Left:      0,400     → 90,1400     (left border)
 *   Right:     1110,400  → 1200,1400   (right border)
 *   Separator: 90,700    → 1110,760    (center strip)
 *   Footer:    0,1460    → 1200,1860   (full width, bottom)
 *
 * The stencil is scaled to 1200×1860 before slicing if needed.
 */
class NewsletterStencilSlicer
{
    /** Reference dimensions. */
    private const REF_W = 1200;

    private const REF_H = 1860;

    /** Crop regions [x, y, width, height] at reference size. */
    private const REGIONS = [
        'header' => [0, 0, 1200, 400],
        'left' => [0, 400, 90, 1000],
        'right' => [1110, 400, 90, 1000],
        'separator' => [90, 700, 1020, 60],
        'footer' => [0, 1460, 1200, 400],
    ];

    /**
     * Slice a stencil image into 5 newsletter components.
     *
     * @param  string  $inputPath  Path to the stencil image
     * @param  string  $outputDir  Directory to save the 5 components
     * @return array Filenames of the 5 generated images
     */
    public static function slice(string $inputPath, string $outputDir): array
    {
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Load and scale to reference size
        $src = imagecreatefromjpeg($inputPath) ?: imagecreatefrompng($inputPath);
        if (! $src) {
            throw new \RuntimeException('Cannot read stencil image');
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        // Scale to reference dimensions
        if ($srcW !== self::REF_W || $srcH !== self::REF_H) {
            $scaled = imagecreatetruecolor(self::REF_W, self::REF_H);
            imagecopyresampled($scaled, $src, 0, 0, 0, 0, self::REF_W, self::REF_H, $srcW, $srcH);
            imagedestroy($src);
            $src = $scaled;
        }

        $files = [];
        foreach (self::REGIONS as $name => [$x, $y, $w, $h]) {
            $crop = imagecreatetruecolor($w, $h);
            imagecopy($crop, $src, 0, 0, $x, $y, $w, $h);

            $filename = "{$name}.jpg";
            $path = rtrim($outputDir, '/')."/{$filename}";
            imagejpeg($crop, $path, 85);
            imagedestroy($crop);

            $files[$name] = $filename;
        }

        imagedestroy($src);

        return $files;
    }

    /**
     * Extract the dominant primary color from the center area of the stencil.
     */
    public static function extractPrimaryColor(string $inputPath): string
    {
        $src = imagecreatefromjpeg($inputPath) ?: imagecreatefrompng($inputPath);
        if (! $src) {
            return '#003366';
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        // Sample the center of the image (should be the flat primary color)
        $cx = (int) ($srcW * 0.5);
        $cy = (int) ($srcH * 0.5);
        $rgb = imagecolorat($src, $cx, $cy);
        imagedestroy($src);

        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
