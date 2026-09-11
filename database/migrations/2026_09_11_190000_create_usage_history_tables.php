<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_send_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('provider');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['date', 'provider']);
        });

        Schema::create('cloudflare_ai_usage_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('model_id');
            $table->unsignedInteger('neurons')->default(0);
            $table->unsignedInteger('requests')->default(0);
            $table->timestamps();

            $table->unique(['date', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloudflare_ai_usage_stats');
        Schema::dropIfExists('mail_send_stats');
    }
};
