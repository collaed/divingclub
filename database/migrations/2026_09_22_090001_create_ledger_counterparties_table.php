<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who the club's bank moves money with. Auto-created the first time a
 * transaction matches a name or IBAN nothing else recognises; a bureau
 * member can then correct the match, name, kind or default category, and
 * every later transaction from the same IBAN or name benefits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_counterparties', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('iban')->nullable()->index();
            $table->string('kind')->default('other'); // member | supplier | federation | venue | other
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('default_category')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_counterparties');
    }
};
