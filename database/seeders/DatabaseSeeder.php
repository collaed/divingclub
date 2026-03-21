<?php

/**
 * Master database seeder — runs during installation.
 *
 * Seeds the standard package data that every diving club needs:
 * roles, member statuses, federations, certification levels, and
 * dive group rules. Club-specific data (members, events, dive sites)
 * is NOT seeded here — that's handled by the CepSeeder and
 * DiveSiteSeeder which are only run for the ClubCEP.eu instance.
 * Run:  php artisan db:seed --class=CepSeeder
 *
 * @author ClubCEP.eu
 */

namespace Database\Seeders;

use App\Models\MemberDetail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,              // bureau_master, bureau_finance, bureau_technical, instructor, member
            MemberStatusSeeder::class,      // active, inactive, suspended, honorary, trial
            FederationSeeder::class,        // FFESSM, LIFRAS, FLASSA (base set)
            CertificationLevelSeeder::class, // All federation cert levels + extended federations
            DiveGroupRuleSeeder::class,     // FFESSM palanquée composition rules
        ]);

        $this->seedAdminUser();
    }

    private function seedAdminUser(): void
    {
        if (User::where('primary_email', 'admin@divingclub.eu')->exists()) {
            return;
        }

        $admin = User::create([
            'username' => 'admin',
            'primary_email' => 'admin@divingclub.eu',
            'password' => Hash::make('password'),
            'role_id' => Role::where('slug', 'bureau_master')->value('id'),
            'status_id' => \App\Models\MemberStatus::where('slug', 'actif')->value('id'),
            'email_verified_at' => now(),
        ]);

        MemberDetail::create([
            'user_id' => $admin->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
        ]);
    }
}
