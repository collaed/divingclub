<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two kinds of tag (see LedgerTag): fixed ones are an approved, stable list
 * (seeded by LedgerTagSeeder, changed only by the treasurer); variable ones
 * carry a blank filled in once per transaction ("for: ___", "covers: ___")
 * so the same fact can be applied to several transactions at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->string('kind')->default('fixed'); // fixed | variable
            $table->string('description')->nullable();
            $table->boolean('proposed')->default(false); // a bureau member's free tag, awaiting treasurer approval
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_tags');
    }
};
