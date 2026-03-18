<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('caption', 500)->nullable();
            $table->unsignedTinyInteger('quality_score')->default(50); // 0-100 auto-rated
            $table->boolean('approved')->default(true);
            $table->timestamps();
        });

        // Add IBAN to theme_settings if not present
        \App\Models\ThemeSetting::firstOrCreate(['key' => 'club_iban'], ['value' => env('CLUB_IBAN', '')]);
        \App\Models\ThemeSetting::firstOrCreate(['key' => 'club_bic'], ['value' => '']);
    }

    public function down(): void
    {
        Schema::dropIfExists('event_photos');
        \App\Models\ThemeSetting::whereIn('key', ['club_iban', 'club_bic'])->delete();
    }
};
