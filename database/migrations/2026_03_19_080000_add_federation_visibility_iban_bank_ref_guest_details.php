<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Federation activation: active/recognized/invisible per club
        Schema::table('federations', function (Blueprint $table) {
            $table->enum('visibility', ['active', 'recognized', 'invisible'])->default('active')->after('full_name');
        });

        // IBAN for members (quicker payment/better reconcile)
        Schema::table('member_details', function (Blueprint $table) {
            $table->string('iban', 34)->nullable()->after('country');
        });

        // Bank statement reference stored with payment on reconciliation
        Schema::table('payment_expected', function (Blueprint $table) {
            $table->string('bank_statement_ref', 100)->nullable()->after('reconciled_at');
            $table->date('bank_statement_date')->nullable()->after('bank_statement_ref');
        });

        // More detail on external registrations (federation guests)
        Schema::table('external_registrations', function (Blueprint $table) {
            $table->string('external_member_phone', 30)->nullable()->after('external_member_email');
            $table->string('external_member_federation', 50)->nullable()->after('external_member_phone');
            $table->string('external_member_licence_no', 50)->nullable()->after('external_member_federation');
            $table->text('external_member_emergency_contact')->nullable()->after('external_member_licence_no');
        });
    }

    public function down(): void
    {
        Schema::table('federations', fn (Blueprint $t) => $t->dropColumn('visibility'));
        Schema::table('member_details', fn (Blueprint $t) => $t->dropColumn('iban'));
        Schema::table('payment_expected', fn (Blueprint $t) => $t->dropColumn(['bank_statement_ref', 'bank_statement_date']));
        Schema::table('external_registrations', fn (Blueprint $t) => $t->dropColumn(['external_member_phone', 'external_member_federation', 'external_member_licence_no', 'external_member_emergency_contact']));
    }
};
