<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * System / seeded articles (e.g. the editable dues footer and home landing)
 * legitimately have no author. Align the database with the Article model, which
 * already documents author_id as nullable, so these content shells can be
 * created without a user. Uses raw SQL guarded per driver to avoid requiring
 * doctrine/dbal for the column change and to stay MySQL + PostgreSQL safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('articles', 'author_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE articles ALTER COLUMN author_id DROP NOT NULL');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE articles MODIFY author_id BIGINT UNSIGNED NULL');
        } else {
            // SQLite (tests): rebuild via the schema builder change().
            Schema::table('articles', function (Blueprint $table): void {
                $table->unsignedBigInteger('author_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Intentionally not reverting to NOT NULL: pre-existing null rows would
        // make it unsafe, and nullability is the model's documented contract.
    }
};
