<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Partner clubs (key exchange)
        Schema::create('club_partnerships', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // "Club Européen de Plongée"
            $table->string('base_url');                // "https://cep.divingclub.eu"
            $table->string('api_key_id', 64)->unique(); // Our key ID they use to call us
            $table->text('api_secret_hash');            // bcrypt of the shared secret
            $table->text('their_api_key_id')->nullable(); // Key ID we use to call them
            $table->text('their_api_secret')->nullable(); // Encrypted secret for outbound calls
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        // Events marked as federated (shareable with partner clubs)
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_federated')->default(false)->after('status');
            $table->unsignedInteger('external_slots')->default(0)->after('is_federated');
        });

        // External registrations from partner clubs
        Schema::create('external_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partnership_id')->constrained('club_partnerships')->cascadeOnDelete();
            $table->string('external_member_name');
            $table->string('external_member_email')->nullable();
            $table->string('external_cert_level')->nullable();  // "LIFRAS P2★" or "FFESSM N2"
            $table->date('external_medical_valid_until')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->text('notes')->nullable();
            $table->string('external_ref')->nullable();   // ID on the remote instance
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_registrations');
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['is_federated', 'external_slots']);
        });
        Schema::dropIfExists('club_partnerships');
    }
};
