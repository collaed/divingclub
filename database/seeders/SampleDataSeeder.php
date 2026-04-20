<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\Event;
use App\Models\MemberDetail;
use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $memberRole = Role::where('slug', 'member')->first();
        $instructorRole = Role::where('slug', 'instructor')->first();
        $actif = MemberStatus::where('slug', 'actif')->first();
        $junior = MemberStatus::where('slug', 'junior')->first();
        $fonctionnaire = MemberStatus::where('slug', 'fonctionnaire')->first();
        $honoraire = MemberStatus::where('slug', 'honoraire')->first();

        $personas = [
            ['Jean-Claude', 'HOFFMANN', 'jc.hoffmann@sample.eu', 'M', 'Luxembourg', 'N4', 350, 2008, $instructorRole, $actif, 'fr', '+352691200001', '1975-03-15'],
            ['Isabelle', 'FERREIRA', 'isabelle.ferreira@sample.eu', 'F', 'Portugal', 'N2', 65, 2019, $memberRole, $actif, 'pt', '+352691200002', '1988-07-22'],
            ['Romain', 'KIEFFER', 'romain.kieffer@sample.eu', 'M', 'Luxembourg', 'MF2', 500, 2005, $instructorRole, $actif, 'lb', '+352691200003', '1970-11-30'],
            ['Nathalie', 'BECKER', 'nathalie.becker@sample.eu', 'F', 'Germany', 'N3', 110, 2016, $memberRole, $fonctionnaire, 'de', '+352691200004', '1982-01-18'],
            ['Dimitri', 'PAPADOPOULOS', 'dimitri.papa@sample.eu', 'M', 'Greece', 'N1', 20, 2024, $memberRole, $junior, 'el', '+352691200005', '2008-05-10'],
            ['Carla', 'SANTOS', 'carla.santos@sample.eu', 'F', 'Portugal', 'N2', 55, 2020, $memberRole, $actif, 'pt', '+352691200006', '1990-09-03'],
            ['Patrick', 'MEYERS', 'patrick.meyers@sample.eu', 'M', 'Belgium', 'N3', 180, 2012, $memberRole, $actif, 'fr', '+352691200007', '1965-12-25'],
            ['Yuki', 'TANAKA', 'yuki.tanaka@sample.eu', 'F', 'Japan', 'N1', 10, 2025, $memberRole, $junior, 'en', '+352691200008', '2010-04-14'],
            ['Roberto', 'CONTI', 'roberto.conti@sample.eu', 'M', 'Italy', 'MF1', 280, 2010, $instructorRole, $actif, 'it', '+352691200009', '1978-08-07'],
            ['Marta', 'KOWALSKA', 'marta.kowalska@sample.eu', 'F', 'Poland', 'N2', 70, 2018, $memberRole, $actif, 'pl', '+352691200010', '1985-06-20'],
            ['Henri', 'DUVAL', 'henri.duval@sample.eu', 'M', 'France', 'N4', 400, 2003, $memberRole, $honoraire, 'fr', '+352691200011', '1955-02-28'],
            ['Alina', 'POPESCU', 'alina.popescu@sample.eu', 'F', 'Romania', 'N1', 5, 2026, $memberRole, $junior, 'ro', '+352691200012', '2009-10-12'],
            ['Marc', 'THILL', 'marc.thill@sample.eu', 'M', 'Luxembourg', 'N3', 150, 2014, $memberRole, $actif, 'lb', '+352691200013', '1980-04-01'],
            ['Svetlana', 'NOVAK', 'svetlana.novak@sample.eu', 'F', 'Czech Republic', 'N2', 45, 2021, $memberRole, $actif, 'cs', '+352691200014', '1992-11-15'],
            ['François', 'LAMBERT', 'francois.lambert@sample.eu', 'M', 'France', 'E2', 600, 2001, $instructorRole, $actif, 'fr', '+352691200015', '1968-07-04'],
            ['Eva', 'SCHNEIDER', 'eva.schneider@sample.eu', 'F', 'Germany', 'N1', 15, 2025, $memberRole, $junior, 'de', '+352691200016', '2007-03-22'],
            ['Pedro', 'OLIVEIRA', 'pedro.oliveira@sample.eu', 'M', 'Portugal', 'N2', 80, 2017, $memberRole, $actif, 'pt', '+352691200017', '1987-09-18'],
            ['Katrin', 'WEBER', 'katrin.weber@sample.eu', 'F', 'Austria', 'N3', 130, 2013, $memberRole, $fonctionnaire, 'de', '+352691200018', '1979-01-30'],
            ['Liam', 'O\'BRIEN', 'liam.obrien@sample.eu', 'M', 'Ireland', 'N1', 25, 2023, $memberRole, $actif, 'en', '+352691200019', '1995-12-08'],
            ['Chloé', 'PETIT', 'chloe.petit@sample.eu', 'F', 'France', 'N2', 50, 2022, $memberRole, $actif, 'fr', '+352691200020', '1993-05-27'],
        ];

        foreach ($personas as $p) {
            $user = User::create([
                'primary_email' => $p[2], 'password' => bcrypt('password'),
                'role_id' => $p[8]->id, 'status_id' => $p[9]->id, 'email_verified_at' => now(),
            ]);
            UserEmail::create(['user_id' => $user->id, 'email' => $p[2], 'is_primary' => true, 'is_verified' => true]);
            MemberDetail::create([
                'user_id' => $user->id, 'first_name' => $p[0], 'last_name' => $p[1],
                'sex' => $p[3], 'nationality' => $p[4], 'certification_level' => $p[5],
                'dive_count' => $p[6], 'adhesion_year' => $p[7], 'preferred_language' => $p[10],
                'phone_mobile' => $p[11], 'date_of_birth' => $p[12],
                'cotisation_years' => ['2025', '2026'],
            ]);
        }

        // Fee components
        MembershipFeeComponent::create(['name' => 'Base Membership', 'slug' => 'base', 'amount' => 120.00, 'is_base' => true]);

        $admin = User::whereHas('detail', fn ($q) => $q->where('first_name', 'admin'))->first()
            ?? User::first();
        $members = User::whereHas('detail')->get();
        MembershipFeeComponent::create(['name' => 'FFESSM Insurance Standard', 'slug' => 'insurance_standard', 'amount' => 33.50, 'is_optional' => true]);
        MembershipFeeComponent::create(['name' => 'FFESSM Insurance Premium', 'slug' => 'insurance_premium', 'amount' => 48.00, 'is_optional' => true]);
        MembershipFeeComponent::create(['name' => 'Double Affiliation (LIFRAS)', 'slug' => 'double_affiliation', 'amount' => 25.00, 'is_optional' => true]);

        // Equipment
        $items = [
            ['BCD Aqualung Pro HD — L', 'bcd', 'AQ-BCD-001', 'L', 'Aqualung', true],
            ['BCD Mares Hybrid — M', 'bcd', 'MA-BCD-002', 'M', 'Mares', true],
            ['BCD Cressi Start — S', 'bcd', 'CR-BCD-003', 'S', 'Cressi', true],
            ['BCD Mares Scuba Ranger — XXXS', 'bcd', 'MA-BCD-004', 'XXXS', 'Mares', true],
            ['Regulator Scubapro MK25 — DIN', 'regulator', 'SP-REG-001', '1', 'Scubapro', true],
            ['Regulator Apeks XTX50 — DIN', 'regulator', 'AP-REG-002', '2', 'Apeks', true],
            ['Regulator Mares Rover R2S — DIN', 'regulator', 'MA-REG-003', '3', 'Mares', true],
            ['Regulator Mares Rover R2S — DIN (enfant)', 'regulator', 'MA-REG-004', '4', 'Mares', true],
            ['Tank 12L Steel', 'tank', 'TK-12L-001', '01', 'Spirotechnique', true],
            ['Tank 12L Steel', 'tank', 'TK-12L-002', '02', 'Spirotechnique', true],
            ['Tank 10L Steel', 'tank', 'TK-10L-003', '03', 'ECS', true],
            ['Tank 15L Steel', 'tank', 'TK-15L-004', '04', 'Spirotechnique', true],
            ['Tank 7L Steel', 'tank', 'TK-7L-005', '05', 'Polaris', true],
            ['Tank Nitrox 10L', 'tank', 'TK-NX-006', 'N1', 'Polaris', true],
            ['Computer Suunto D5', 'computer', 'SU-D5-001', null, 'Suunto', false],
            ['Lamp Scubapro Nova 850', 'other', 'SP-LMP-001', null, 'Scubapro', false],
        ];
        foreach ($items as $i) {
            $eq = Equipment::create([
                'name' => $i[0], 'type' => $i[1], 'serial_number' => $i[2],
                'short_number' => $i[3], 'brand' => $i[4],
                'purchase_date' => now()->subYears(rand(1, 5)),
                'condition' => 'good', 'status' => 'available',
                'is_loanable' => $i[5], 'location' => 'Warehouse',
                'is_child_sized' => str_contains($i[0], 'XXXS') || str_contains($i[0], 'enfant'),
                'is_cold_water' => str_contains($i[0], 'XTX50'),
            ]);
        }

        // Events — upcoming pool sessions, dives, theory
        $eventData = [
            ['Wednesday Pool — Block 1', 'pool', now()->next('Wednesday'), '17:00', '18:30', 'Pool Merl'],
            ['Wednesday Pool — Block 2', 'pool_pn1', now()->next('Wednesday'), '18:30', '20:00', 'Pool Merl'],
            ['Friday Apnea', 'apnea', now()->next('Friday'), '18:00', '20:00', 'Pool Merl'],
            ['Quarry Dive — Carrière Tossiat', 'quarry', now()->next('Saturday'), '09:00', '16:00', 'Carrière de Tossiat'],
            ['Theory — Nitrox', 'theory', now()->next('Wednesday')->addWeek(), '18:30', '20:00', 'Pool Merl'],
            ['Long Trip — Sardinia', 'long_trip', now()->addWeeks(6), '08:00', null, 'Sardinia, Italy'],
            ['Monday Swimming — Steinfort', 'pool_swimming', now()->next('Monday'), '19:00', '20:30', 'Steinfort Pool'],
            ['Fosse Training', 'fosse', now()->addWeeks(2)->next('Saturday'), '10:00', '12:00', 'Nemo33, Brussels'],
        ];
        foreach ($eventData as $ed) {
            $event = Event::create([
                'title' => $ed[0], 'event_type' => $ed[1], 'event_date' => $ed[2],
                'event_time' => $ed[3], 'end_time' => $ed[4], 'location' => $ed[5],
                'status' => 'scheduled', 'max_participants' => $ed[1] === 'long_trip' ? 12 : 20,
                'waiting_list_enabled' => true, 'created_by' => $admin->id,
            ]);
            // Register a few members
            foreach ($members->random(min(5, $members->count())) as $m) {
                $event->registrations()->create(['user_id' => $m->id, 'status' => 'confirmed']);
            }
        }

        // Articles
        $articles = [
            ['Welcome to the Club', 'page', 'Welcome to our diving club! We offer training from beginner to instructor level, with weekly pool sessions, open water dives, and international trips.', true],
            ['Training Schedule 2026', 'page', "Pool sessions every Wednesday (17:00-20:00) and Friday (Apnea 18:00-20:00).\nMonday swimming at Steinfort.\nQuarry dives on selected Saturdays.", true],
            ['Safety Guidelines', 'page', 'All divers must have a valid medical certificate. Equipment checks are mandatory before every dive. Buddy system is always enforced.', true],
            ['BCD for Sale — Scubapro Hydros Pro', 'classified', 'Selling my Scubapro Hydros Pro BCD, size M, excellent condition. Used for 50 dives. Asking €350.', false],
        ];
        foreach ($articles as $a) {
            Article::create([
                'title' => $a[0], 'slug' => Str::slug($a[0]).'-'.rand(100, 999),
                'article_type' => $a[1], 'body' => $a[2],
                'is_published' => true, 'is_public' => $a[3],
                'author_id' => $admin->id,
            ]);
        }

        // Equipment loans — a couple of active loans
        $loanableEquipment = Equipment::where('is_loanable', true)->where('status', 'available')->take(2)->get();
        foreach ($loanableEquipment as $eq) {
            $borrower = $members->random();
            EquipmentLoan::create([
                'equipment_id' => $eq->id, 'user_id' => $borrower->id,
                'loaned_at' => now()->subDays(rand(1, 7)), 'loaned_by' => $admin->id,
            ]);
            $eq->update(['status' => 'on_loan', 'last_seen_at' => now()]);
        }

        $this->command->info('Sample data seeded: 20 personas, 4 fee components, '.count($items).' equipment, '.count($eventData).' events, '.count($articles).' articles, 2 active loans.');
    }
}
