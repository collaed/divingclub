<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->year('year');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('season_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_adhoc')->default(false);
            $table->timestamps();
        });

        Schema::create('season_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Mon..6=Sun
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('event_type');
            $table->string('title');
            $table->string('location')->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            $table->string('color_hex', 7)->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('color_hex', 7)->nullable();
            $table->string('event_type'); // pool, dive, training, theory, social
            $table->date('event_date');
            $table->time('event_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('end_date')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users');
            $table->unsignedInteger('max_participants')->nullable();
            $table->boolean('waiting_list_enabled')->default(true);
            $table->timestamp('inscription_open_at')->nullable();
            $table->boolean('inscriptions_closed')->default(false);
            $table->boolean('levels_display')->default(false);
            $table->boolean('confirmation_required')->default(false);
            $table->decimal('estimated_cost', 8, 2)->nullable();
            $table->date('deposit_1_date')->nullable();
            $table->decimal('deposit_1_amount', 8, 2)->nullable();
            $table->date('deposit_2_date')->nullable();
            $table->decimal('deposit_2_amount', 8, 2)->nullable();
            $table->date('deposit_3_date')->nullable();
            $table->decimal('deposit_3_amount', 8, 2)->nullable();
            $table->foreignId('instructor_id')->nullable()->constrained('users');
            $table->json('assistant_ids')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->date('permissions_expire_date')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, cancelled, completed
            $table->foreignId('season_id')->nullable()->constrained();
            $table->string('participant_email')->nullable();
            $table->timestamps();

            $table->index('event_date');
            $table->index('status');
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('confirmed'); // confirmed, waiting, cancelled
            $table->unsignedInteger('waiting_list_position')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
        Schema::dropIfExists('season_patterns');
        Schema::dropIfExists('season_holidays');
        Schema::dropIfExists('seasons');
    }
};
