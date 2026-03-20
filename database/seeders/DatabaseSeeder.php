<?php

/**
 * Master database seeder — runs during installation.
 *
 * Seeds the standard package data that every diving club needs:
 * roles, member statuses, federations, certification levels, and
 * dive group rules. Club-specific data (members, events, dive sites)
 * is NOT seeded here — that's handled by the CepMemberSeeder and
 * DiveSiteSeeder which are only run for the ClubCEP.eu instance.
 *
 * @author ClubCEP.eu
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;

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
    }
}
