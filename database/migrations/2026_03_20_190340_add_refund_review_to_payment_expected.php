<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_expected', function (Blueprint $table) {
            $table->boolean('refund_review_needed')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payment_expected', function (Blueprint $table) {
            $table->dropColumn('refund_review_needed');
        });
    }
};
