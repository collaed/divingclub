<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One imported bank line. `dedup_hash` (transaction date + amount + running
 * balance + statement number) is what stops the same statement, or an
 * overlapping export, from being imported twice — that triple is already
 * effectively unique on a real account history (see
 * LedgerStatementImportService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->id();
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('running_balance', 10, 2)->nullable();
            $table->string('statement_no')->nullable(); // "Extrait"
            $table->string('operation_type')->nullable(); // VIR, FPZ, ...
            $table->string('communication_1')->nullable();
            $table->string('communication_2')->nullable();
            $table->string('communication_3')->nullable();
            $table->string('communication_4')->nullable();
            $table->string('beneficiary_account')->nullable();
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_address')->nullable();
            $table->string('counterparty_locality')->nullable();
            $table->foreignId('counterparty_id')->nullable()->constrained('ledger_counterparties')->nullOnDelete();

            $table->string('category')->nullable(); // Rubriques code, e.g. "21" — see config/ledger.php
            $table->string('state')->default('unknown'); // expected | recognised | confirm | unknown | loop
            $table->string('state_reason')->nullable(); // one line explaining the classifier's guess, shown to the bureau

            $table->foreignId('operation_id')->nullable()->constrained('ledger_operations')->nullOnDelete();
            $table->string('suggested_group')->nullable(); // a name-only guess from config('ledger.groups') — a human still assigns it

            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('source_file')->nullable();
            $table->string('dedup_hash', 64)->unique();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['transaction_date', 'statement_no']);
            $table->index('state');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
