<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named exceptions on top of (or instead of) the criteria match — see
     * MailAliasCriteria::matchingUsers().
     */
    public function up(): void
    {
        // No DB-level default: MySQL rejects a literal DEFAULT on a JSON
        // column ("BLOB/TEXT/GEOMETRY/JSON column can't have a default
        // value"). MailAliasDefinition::$attributes supplies '[]' instead.
        Schema::table('mail_alias_definitions', function (Blueprint $table) {
            $table->json('include_user_ids')->after('criteria');
            $table->json('exclude_user_ids')->after('include_user_ids');
        });
    }

    public function down(): void
    {
        Schema::table('mail_alias_definitions', function (Blueprint $table) {
            $table->dropColumn(['include_user_ids', 'exclude_user_ids']);
        });
    }
};
