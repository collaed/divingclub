<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_intakes', function (Blueprint $table) {
            $table->json('import_result')->nullable()->after('transactions_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_intakes', function (Blueprint $table) {
            $table->dropColumn('import_result');
        });
    }
};
