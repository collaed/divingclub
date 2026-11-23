<?php

use App\Models\MailAliasDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named exceptions on top of (or instead of) the criteria match — see
     * MailAliasCriteria::matchingUsers().
     *
     * Nullable, with no DB-level default: MySQL rejects a literal DEFAULT on
     * a JSON column ("BLOB/TEXT/GEOMETRY/JSON column can't have a default
     * value"), and a NOT NULL add would fail on Postgres against any rows
     * already in the table. Existing rows are backfilled below;
     * MailAliasDefinition::$attributes supplies '[]' for every new one.
     */
    public function up(): void
    {
        Schema::table('mail_alias_definitions', function (Blueprint $table) {
            $table->json('include_user_ids')->nullable()->after('criteria');
            $table->json('exclude_user_ids')->nullable()->after('include_user_ids');
        });

        MailAliasDefinition::withoutTimestamps(function () {
            MailAliasDefinition::query()->get(['id'])->each(
                fn (MailAliasDefinition $d) => $d->forceFill(['include_user_ids' => [], 'exclude_user_ids' => []])->save()
            );
        });
    }

    public function down(): void
    {
        Schema::table('mail_alias_definitions', function (Blueprint $table) {
            $table->dropColumn(['include_user_ids', 'exclude_user_ids']);
        });
    }
};
