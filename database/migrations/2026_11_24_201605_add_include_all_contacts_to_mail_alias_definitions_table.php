<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A "prospects" style group: every currently-subscribed
     * App\Models\MailListContact, without having to name each one in
     * include_contact_ids — see MailAliasService::resolveDefinition().
     */
    public function up(): void
    {
        Schema::table('mail_alias_definitions', function (Blueprint $table) {
            $table->boolean('include_all_contacts')->default(false)->after('include_contact_ids');
        });
    }

    public function down(): void
    {
        Schema::table('mail_alias_definitions', function (Blueprint $table) {
            $table->dropColumn('include_all_contacts');
        });
    }
};
