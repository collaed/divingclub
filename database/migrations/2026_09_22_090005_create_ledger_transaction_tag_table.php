<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transaction_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_tag_id')->constrained()->cascadeOnDelete();
            $table->string('value')->nullable(); // the filled-in blank of a variable tag, e.g. "for: Luca G."
            $table->timestamps();

            $table->unique(['ledger_transaction_id', 'ledger_tag_id'], 'ledger_tx_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transaction_tag');
    }
};
