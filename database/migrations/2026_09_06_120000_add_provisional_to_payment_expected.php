<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_expected', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_expected', 'provisional')) {
                // A member who is not yet classified by the bureau can still
                // self-commit to a computed dues figure. Such rows are flagged
                // provisional so the bureau reviews the classification during
                // reconciliation.
                $table->boolean('provisional')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_expected', function (Blueprint $table): void {
            if (Schema::hasColumn('payment_expected', 'provisional')) {
                $table->dropColumn('provisional');
            }
        });
    }
};
