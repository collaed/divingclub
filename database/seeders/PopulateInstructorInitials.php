<?php

namespace Database\Seeders;

use App\Models\MemberDetail;
use Illuminate\Database\Seeder;

class PopulateInstructorInitials extends Seeder
{
    public function run(): void
    {
        // 1. Fix wrongly flagged instructors — these are NOT instructors
        $notInstructors = [
            'Alain|SMETS', 'Bénédicte|BALOT', 'Bettina|BERGHS', 'Etienne|COUPEZ',
            'Guy|LE GLOAN', 'Jean-Michel|GAVANIER', 'Jürgen|SCHMID', 'Kadri|SIILANARUSK',
            'Laura|FEVRIER', 'Luís|ALBUQUERQUE', 'Marco|MARCOS', 'Monica|PACE',
            'Philippe|PIPEAUX', 'Peter|BASCH', 'Ricardo|SELVES', 'Tibor|BALOGH',
            'Vesa|TANNER', 'Volker|HOYER',
        ];

        $demoted = 0;
        foreach ($notInstructors as $key) {
            [$fn, $ln] = explode('|', $key);
            $d = MemberDetail::where('first_name', $fn)->where('last_name', $ln)->first();
            if ($d && $d->active_instructor) {
                $d->update(['active_instructor' => false]);
                $this->command->info("  Demoted: {$fn} {$ln}");
                $demoted++;
            }
        }
        $this->command->info("{$demoted} non-instructors demoted.");

        // 2. Set initials and colors from old Google Sheet planning
        // Jerome Samson was first → gets J. Tongio → T, Boisseau → B.
        $map = [
            'Gilles|SALETEN' => ['G', '#e69138'],
            'Lahoucen|OUZBAD' => ['L', '#6aa84f'],
            'Eric|RICHARD' => ['E', '#3d85c6'],
            'Michel|BROCHARD' => ['M', '#cc0000'],
            'Pietro|GIANCOLA' => ['O', '#674ea7'],
            'Vincent|GIRARD' => ['V', '#45818e'],
            'Keran|CHAUSSARD' => ['K', '#f1c232'],
            'Jerome|SAMSON' => ['J', '#38761d'],
            'Jerome|TONGIO' => ['T', '#ff6d01'],
            'Jérôme|BOISSEAU' => ['B', '#0b5394'],
            'Pascale|LUCIETTO' => ['P', '#c27ba0'],
            'Nicolas|FEVRIER' => ['N', '#76a5af'],
            'Sébastien|JACQUES' => ['S', '#93c47d'],
            'Manuel|MONTEIRO' => ['U', '#8e7cc3'],
            'Frederic|COUSIN' => ['F', '#e06666'],
            'Valérie|JOND' => ['A', '#d5a6bd'],
            'Luc|MASSON' => ['C', '#999999'],
        ];

        $updated = 0;
        foreach ($map as $key => $data) {
            [$fn, $ln] = explode('|', $key);
            $d = MemberDetail::where('first_name', $fn)->where('last_name', $ln)->first();
            if ($d) {
                $d->update(['instructor_initial' => $data[0], 'instructor_color' => $data[1]]);
                $this->command->info("  {$fn} {$ln} => {$data[0]} ({$data[1]})");
                $updated++;
            }
        }
        $this->command->info("{$updated} instructors updated with initials and colors.");
    }
}
