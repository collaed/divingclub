<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cotisation-tagged transaction can cover more than one member (a couple
 * or a parent and child paying together in one transfer) — so this is a
 * separate many-to-many, not a single counterparty_id, and linking it to
 * one member never excludes it as a candidate for another.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transaction_member', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ledger_transaction_id', 'user_id'], 'ledger_tx_member_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transaction_member');
    }
};
