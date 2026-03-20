<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->string('statement_ref', 100)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('statement_ref');
        });
    }
};
