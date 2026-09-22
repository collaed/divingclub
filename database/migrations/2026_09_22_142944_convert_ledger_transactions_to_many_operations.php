<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A transaction can exceptionally belong to more than one operation — e.g. one
 * van-rental invoice split between two separate outings (Todi and Graviere).
 * Replaces the single `operation_id` column with a pivot table; every existing
 * assignment is preserved as its first row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_operation_transaction', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_operation_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ledger_transaction_id', 'ledger_operation_id'], 'ledger_op_tx_unique');
        });

        DB::table('ledger_transactions')->whereNotNull('operation_id')->orderBy('id')->get()
            ->each(function ($row): void {
                DB::table('ledger_operation_transaction')->insert([
                    'ledger_transaction_id' => $row->id,
                    'ledger_operation_id' => $row->operation_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('ledger_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('operation_id');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table): void {
            $table->foreignId('operation_id')->nullable()->after('state_reason')->constrained('ledger_operations')->nullOnDelete();
        });

        // Best-effort: a transaction with several operations after the up() keeps only one.
        DB::table('ledger_operation_transaction')->orderBy('id')->get()->unique('ledger_transaction_id')
            ->each(fn ($row) => DB::table('ledger_transactions')->where('id', $row->ledger_transaction_id)->update(['operation_id' => $row->ledger_operation_id]));

        Schema::dropIfExists('ledger_operation_transaction');
    }
};
