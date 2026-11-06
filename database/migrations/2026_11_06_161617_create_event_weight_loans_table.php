<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weights aren't individually numbered Equipment — they're lent and
 * returned as a quantity, tracked per event rather than per borrower (one
 * event's weight pool, not "Jean's 2 weights" vs "Marie's 3 weights").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_weight_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_registration_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedSmallInteger('quantity_returned')->default(0);
            $table->foreignId('loaned_by')->nullable()->constrained('users');
            $table->timestamp('loaned_at');
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_weight_loans');
    }
};
