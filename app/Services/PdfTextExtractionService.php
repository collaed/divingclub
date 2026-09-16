<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Extract text from a PDF: pdftotext first (works for any digital/text-based
 * PDF, which covers most bank statements and generated licence cards),
 * falling back to Tesseract OCR for a scanned/photographed one.
 *
 * Moved out of BankReconciliationService so the document-intake classifier
 * can reuse it too, rather than running its own separate OCR pass over the
 * same file.
 */
class PdfTextExtractionService
{
    public function extract(string $pdfPath): string
    {
        // Strategy 1: pdftotext (works for digital/text-based PDFs)
        $textPath = tempnam(sys_get_temp_dir(), 'pdftext_').'.txt';
        $escaped = escapeshellarg($pdfPath);
        $escapedOut = escapeshellarg($textPath);
        exec("pdftotext -layout {$escaped} {$escapedOut} 2>/dev/null", $output, $code);

        if ($code === 0 && file_exists($textPath)) {
            $text = file_get_contents($textPath);
            @unlink($textPath);

            // If pdftotext returned meaningful content, use it
            if (strlen(trim($text)) > 50) {
                return $text;
            }
        }
        @unlink($textPath);

        // Strategy 2: Tesseract OCR (for scanned PDFs)
        $imgDir = tempnam(sys_get_temp_dir(), 'pdftext_img_');
        @unlink($imgDir);
        @mkdir($imgDir);

        try {
            // Convert PDF pages to images
            exec("pdftoppm -png -r 300 {$escaped} {$imgDir}/page 2>/dev/null", $output, $code);

            if ($code !== 0) {
                return '';
            }

            $pages = glob("{$imgDir}/page-*.png");
            sort($pages);
            $fullText = '';

            foreach ($pages as $page) {
                $escapedPage = escapeshellarg($page);
                $ocrLines = [];
                exec("tesseract {$escapedPage} stdout -l fra+deu+eng 2>/dev/null", $ocrLines, $ocrCode);
                if ($ocrCode === 0) {
                    $fullText .= implode("\n", $ocrLines)."\f";
                }
            }

            return $fullText;
        } finally {
            @array_map('unlink', glob("{$imgDir}/*"));
            @rmdir($imgDir);
        }
    }
}
