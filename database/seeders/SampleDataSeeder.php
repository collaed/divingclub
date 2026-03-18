<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\EquipmentMaintenance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MemberDetail;
use App\Models\MemberLicence;
use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use App\Models\PaymentExpected;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Database\Seeder;

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
        MembershipFeeComponent::create(['name' => 'FFESSM Insurance Standard', 'slug' => 'insurance_standard', 'amount' => 33.50, 'is_optional' => true]);
        MembershipFeeComponent::create(['name' => 'FFESSM Insurance Premium', 'slug' => 'insurance_premium', 'amount' => 48.00, 'is_optional' => true]);
        MembershipFeeComponent::create(['name' => 'Double Affiliation (LIFRAS)', 'slug' => 'double_affiliation', 'amount' => 25.00, 'is_optional' => true]);

        // Equipment
        $items = [
            ['BCD Aqualung Pro HD', 'bcd', 'AQ-BCD-001'], ['BCD Mares Hybrid', 'bcd', 'MA-BCD-002'],
            ['Regulator Scubapro MK25', 'regulator', 'SP-REG-001'], ['Regulator Apeks XTX50', 'regulator', 'AP-REG-002'],
            ['Tank 12L Steel', 'tank', 'TK-12L-001'], ['Tank 12L Steel', 'tank', 'TK-12L-002'],
            ['Tank 15L Steel', 'tank', 'TK-15L-001'], ['Computer Suunto D5', 'computer', 'SU-D5-001'],
        ];
        foreach ($items as $i) {
            Equipment::create(['name' => $i[0], 'type' => $i[1], 'serial_number' => $i[2], 'purchase_date' => now()->subYears(rand(1, 5)), 'condition' => 'good']);
        }

        $this->command->info('Sample data seeded: 20 personas, 4 fee components, 8 equipment items.');
    }
}
