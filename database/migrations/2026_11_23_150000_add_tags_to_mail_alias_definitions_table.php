<?php

use App\Models\MailAliasDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the single unique `tag` with a `tags` array, so a definition
     * can answer to several routing tags (e.g. "bureau" + "board") without
     * duplicating its criteria and risking the two drifting apart.
     */
    public function up(): void
    {
        Schema::table('mail_alias_definitions', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('tag');
        });

        MailAliasDefinition::withoutTimestamps(function () {
            MailAliasDefinition::query()->get(['id', 'tag'])->each(
                fn (MailAliasDefinition $d) => $d->forceFill(['tags' => [$d->tag]])->save()
            );
        });

        Schema::table('mail_alias_definitions', function (Blueprint $table) {
            $table->dropColumn('tag');
        });
    }

    public function down(): void
    {
        Schema::table('mail_alias_definitions', function (Blueprint $table) {
            $table->string('tag')->nullable()->after('id');
        });

        MailAliasDefinition::withoutTimestamps(function () {
            MailAliasDefinition::query()->get(['id', 'tags'])->each(
                fn (MailAliasDefinition $d) => $d->forceFill(['tag' => $d->tags[0] ?? null])->save()
            );
        });

        Schema::table('mail_alias_definitions', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
