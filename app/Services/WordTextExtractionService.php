<?php

declare(strict_types=1);

namespace App\Services;

use ZipArchive;

/**
 * Extract plain text from a .docx file: it's a zip archive whose
 * word/document.xml holds the body as XML. No new dependency — PHP's
 * built-in ZipArchive is enough for a well-formed, typed document (not a
 * scan), which is all the compte-rendu extraction task needs.
 *
 * Legacy binary .doc files aren't supported (no converter is installed on
 * either server) — callers should treat a null/empty result as "skip".
 */
class WordTextExtractionService
{
    public function extract(string $docxPath): string
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return '';
        }

        // Paragraph and line breaks become newlines before tags are stripped,
        // so extracted actions don't run different paragraphs together.
        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<w:br\s*\/?>/', "\n", $xml) ?? $xml;

        $text = strip_tags($xml);

        return trim(html_entity_decode($text, ENT_QUOTES | ENT_XML1));
    }
}
