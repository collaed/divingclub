<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('member_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->decimal('fee_multiplier', 4, 2)->default(1.00);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique()->nullable();
            $table->string('primary_email')->unique();
            $table->string('password')->nullable();
            $table->foreignId('role_id')->constrained('roles');
            $table->foreignId('status_id')->nullable()->constrained('member_statuses');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_login_attempts');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('member_statuses');
        Schema::dropIfExists('roles');
    }
};
