<?php

use App\Models\MailAliasDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named exceptions on top of the criteria match, this time against
     * App\Models\MailListContact rather than User — see
     * MailAliasService::resolveDefinition(). Same nullable/no-default/
     * backfill shape as include_user_ids/exclude_user_ids, for the same
     * reasons (MySQL rejects a JSON column DEFAULT; Postgres rejects a
     * NOT NULL add against a non-empty table).
     */
    public function up(): void
    {
        Schema::table('mail_alias_definitions', function (Blueprint $table) {
            $table->json('include_contact_ids')->nullable()->after('exclude_user_ids');
        });

        MailAliasDefinition::withoutTimestamps(function () {
            MailAliasDefinition::query()->get(['id'])->each(
                fn (MailAliasDefinition $d) => $d->forceFill(['include_contact_ids' => []])->save()
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mail_alias_definitions', function (Blueprint $table) {
            $table->dropColumn('include_contact_ids');
        });
    }
};
