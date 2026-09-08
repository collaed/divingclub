<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('guard')->nullable();
            $table->boolean('remember')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_records');
    }
};
