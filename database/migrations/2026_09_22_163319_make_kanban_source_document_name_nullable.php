<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A card added by hand from the board (not extracted from a compte-rendu)
 * has no source document — null is how KanbanCard::isManual() tells the two
 * apart. Uses raw SQL guarded per driver to avoid requiring doctrine/dbal
 * for the column change and to stay MySQL + PostgreSQL safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE kanban_cards ALTER COLUMN source_document_name DROP NOT NULL');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE kanban_cards MODIFY source_document_name VARCHAR(255) NULL');
        } else {
            // SQLite (tests): rebuild via the schema builder change().
            Schema::table('kanban_cards', function (Blueprint $table): void {
                $table->string('source_document_name')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Intentionally not reverting to NOT NULL: any manually-added card
        // (null by design) would make it unsafe.
    }
};
