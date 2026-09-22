<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A group of transactions that belong together and have a running net: a
 * closed loop (pass-through money that should net to zero), a trip (settles
 * once, ideally close to its budget), or an ongoing cost centre (never
 * closes, tracked against a yearly budget). See LedgerOperation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('kind')->default('other'); // loop | trip | cost_centre | other
            $table->string('status')->default('open'); // open | closed
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->decimal('budget_amount', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_operations');
    }
};
