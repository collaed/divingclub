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
        Schema::create('mail_alias_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('tag')->unique();
            $table->string('label');
            $table->json('criteria');
            $table->string('auth_level')->default('bureau');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_alias_definitions');
    }
};
