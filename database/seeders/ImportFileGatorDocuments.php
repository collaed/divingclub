<?php

namespace Database\Seeders;

use App\Models\LibraryFile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ImportFileGatorDocuments extends Seeder
{
    private int $imported = 0;

    private int $skipped = 0;

    private const VISIBILITY_MAP = [
        'Bureau' => 'bureau',
        'Compta' => 'bureau',
        'Moniteurs' => 'instructors',
        'Divers' => 'members',
    ];

    public function run(): void
    {
        $srcBase = env('FILEGATOR_PATH', '/home/collaed/tmp/domains/clubcep.eu/public_html/documents');

        if (! is_dir($srcBase)) {
            $this->command->error("Source not found: {$srcBase}");

            return;
        }

        foreach (self::VISIBILITY_MAP as $topFolder => $visibility) {
            $srcDir = "{$srcBase}/{$topFolder}";
            if (! is_dir($srcDir)) {
                $this->command->warn("Skipping {$topFolder}: not found");

                continue;
            }
            $this->command->info("Importing {$topFolder}/ → visibility={$visibility}");
            $this->importDirectory($srcDir, $topFolder, $visibility);
        }

        $this->command->info("Done: {$this->imported} imported, {$this->skipped} skipped");
    }

    private function importDirectory(string $srcDir, string $folder, string $visibility): void
    {
        $items = @scandir($srcDir);
        if (! $items) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '__MACOSX' || $item === '.htaccess' || $item === '.DS_Store') {
                continue;
            }

            $fullPath = "{$srcDir}/{$item}";

            if (is_dir($fullPath)) {
                $this->importDirectory($fullPath, "{$folder}/{$item}", $visibility);

                continue;
            }

            // Skip if already imported
            $storagePath = "library/{$folder}/{$item}";
            if (LibraryFile::where('path', $storagePath)->exists()) {
                $this->skipped++;

                continue;
            }

            // Copy to storage
            $destDir = storage_path("app/public/library/{$folder}");
            if (! is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            @copy($fullPath, storage_path("app/public/{$storagePath}"));

            LibraryFile::create([
                'filename' => Str::slug(pathinfo($item, PATHINFO_FILENAME)).'.'.pathinfo($item, PATHINFO_EXTENSION),
                'original_name' => $item,
                'path' => $storagePath,
                'mime_type' => mime_content_type($fullPath) ?: 'application/octet-stream',
                'size' => filesize($fullPath),
                'folder' => $folder,
                'visibility' => $visibility,
                'uploaded_by' => 1,
            ]);
            $this->imported++;
        }
    }
}
