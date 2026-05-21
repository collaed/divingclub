<?php

/**
 * Import FLASSA licences and update medical cert expiry dates.
 *
 * Run: php artisan tinker --execute 'require "database/seeders/import_flassa.php";'
 */

use App\Models\Document;
use App\Models\Federation;
use App\Models\MemberLicence;
use App\Models\User;

echo "🏊 Importing FLASSA licences...\n\n";

$flassa = Federation::where('acronym', 'FLASSA')->first();

// FLASSA licence data from the spreadsheet
// [lastname, firstname, email_match, licence_no, cert_date, flassa_expiry, status, comment]
$data = [
    ['COLLART', 'Mafalda', 'm.collart@eib.org', 'LS-0399', '2025-09-23', '2026-12-31', 'ok', ''],
    ['MARCOS', 'Marta', 'martamarlo99@gmail.com', 'LS-0400', '2025-10-02', '2027-12-31', 'ok', ''],
    ['SONDT', 'Martine', 'masondt78@yahoo.fr', 'LS-0401', '2025-09-03', '2026-12-31', 'ok', ''],
    ['SALETEN', 'Gilles', 'gilles.saleten@wanadoo.fr', 'LS-0402', '2025-12-12', '2026-12-31', 'ok', ''],
    ['MARCOS', 'Marco', 'marcosmarco3@gmail.com', 'LS-0403', '2025-10-14', '2026-12-31', 'ok', ''],
    ['SCHMID', 'Jürgen', 'jschmid@pt.lu', 'LS-0404', '2025-10-30', '2026-12-31', 'ok', ''],
    ['DEMONNAZ', 'Fabien', 'fdemonnaz@gmail.com', 'LS-0405', '2025-09-19', '2026-12-31', 'ok', ''],
    ['VLAIC', 'Paula', 'mpvlaic@gmail.com', 'LS-0406', '2025-09-19', '2026-12-31', 'ok', ''],
    ['VIGNERON', 'Frédérique', 'vigneron_f@yahoo.com', 'LS-0407', '2025-11-06', '2026-12-31', 'ok', ''],
    ['CHAUSSARD', 'Keran', 'kchaussard@tti-network.com', 'LS-0408', '2025-09-02', '2026-12-31', 'ok', ''],
    ['COLLART', 'Eddy', 'eddy.collart@gmail.com', 'LS-0409', '2026-01-15', '2026-12-31', 'ok', ''],
    ['JACQUES', 'Sébastien', 'sjacques@biodiving.eu', 'LS-0410', '2026-01-20', '2026-12-31', 'ok', ''],
    ['BROCHARD', 'Michel', 'michel.brochard@mac.com', 'LS-0411', '2025-10-03', '2026-12-31', 'ok', ''],
    ['TONGIO', 'Jerome', 'jerome.tongio@gmail.com', 'LS-0412', '2025-10-13', '2026-12-31', 'ok', ''],
    ['SENJA', 'Oksana', 'oksanasenja@hotmail.com', 'LS-0413', '2025-10-14', '2027-12-31', 'ok', ''],
    ['CHAUSSARD', 'Louis', 'dvarban@tti-network.com', 'LS-0430', '2025-09-02', '2026-12-31', 'ok', 'mineur gratuite'],
    ['COUPEZ', 'Etienne', 'etienne.coupez@gmail.com', 'LS-0431', '2025-09-22', '2026-12-31', 'ok', ''],
    ['GIRARD', 'Vincent', 'girard.vincent@gmail.com', 'LS-0432', '2025-10-02', '2027-12-31', 'ok', ''],
    ['MONTEIRO', 'Manuel', 'monteirolmanuel@yahoo.fr', 'LS-0433', '2025-10-20', '2026-12-31', 'ok', ''],
    ['BERGHS', 'Bettina', 'bberghs@pt.lu', null, '2025-10-07', '2026-12-31', 'non valable', 'cases non cochées'],
    ['MANGENOT', 'Marie-Jo', 'mmariejo@gmail.com', 'LS-0434', '2025-11-03', '2026-12-31', 'ok', ''],
    ['GODFRIN', 'Lilian', 'lilian.godfrin@gmail.com', null, '2025-11-22', '2026-12-31', 'non valable', 'manque cases + cachet'],
    ['KRAEMER', 'Roger', 'rogerk210@gmail.com', null, '2025-11-27', '2026-12-31', 'non valable', 'mauvais formulaire'],
    ['MASSON', 'Luc', 'luc.masson@capfloor.lu', null, '2025-09-24', '2026-12-31', 'non valable', 'mauvais formulaire + cachet'],
    ['RAVACCHIOLI', 'Yanis', 'yanis.ravacchioli@gmail.com', null, '2025-09-16', '2026-12-31', 'non valable', 'mauvais formulaire'],
    ['FEVRIER', 'Laura', 'lafevrier@gmail.com', 'LS-0435', '2025-09-24', '2026-12-31', 'ok', ''],
    ['BROOM', 'Nicolo', 'mrsbroom2005@gmail.com', 'LS-0436', '2025-09-24', '2026-12-31', 'ok', 'mineur gratuite'],
    ['MELLONCELLI', 'Mauro', 'mauro.melloncelli@gmail.com', 'LS-0437', '2025-09-29', '2026-12-31', 'ok', ''],
    ['FEVRIER', 'Nicolas', 'nifevrier@gmail.com', 'LS-0438', '2025-10-01', '2026-12-31', 'ok', ''],
    ['FEVRIER', 'Emilie', 'lafevrier+Emilie@gmail.com', 'LS-0441', '2025-10-01', '2026-12-31', 'ok', 'mineur gratuite'],
    ['GIRARD', 'Celeste', 'celeste.girard.11@gmail.com', 'LS-0447', '2025-10-02', '2026-12-31', 'ok', 'mineur gratuite'],
    ['TANNER', 'Vesa', 'vesa.tanner@ec.europa.eu', 'LS-0439', '2025-10-07', '2026-12-31', 'ok', ''],
    ['MICHAILIDIS', 'Ionas', 'michailidis@gmail.com', 'LS-0440', '2025-10-30', '2026-12-31', 'ok', ''],
    ['SAARE', 'Ene', 'esaare@hotmail.com', null, '2025-11-07', '2027-12-31', 'non valable', 'mauvais formulaire'],
    ['COUSIN', 'Arthur', 'Artur.cousin@gmail.com', null, '2025-11-07', '2026-12-31', 'non valable', 'mauvais formulaire'],
    ['COUSIN', 'Annette', 'Ene.fred@gmail.com', null, '2025-11-07', '2026-12-31', 'non valable', 'mauvais formulaire'],
    ['GERMAN', 'Kristina', 'kristina_german@hotmail.com', 'LS-0488', '2025-10-07', '2027-12-31', 'ok', ''],
    ['TONGIO', 'Jules', 'julestongiobilocq@gmail.com', 'LS-0489', '2025-10-15', '2026-12-31', 'ok', 'mineur gratuite'],
    ['PIPEAUX', 'Philippe', 'philippe.pipeaux@gmail.com', 'LS-0490', '2025-11-07', '2026-12-31', 'ok', ''],
    ['BASCH', 'Peter', 'bpeter56@yahoo.co.uk', 'LS-0491', '2025-11-10', '2026-12-31', 'ok', ''],
    ['BONTEA', 'Aurel', 'aurel.bontea@ec.europa.eu', 'LS-0492', '2025-12-02', '2026-12-31', 'ok', ''],
    ['BONTEA', 'Daniel', 'bonteadaniel@gmail.com', 'LS-0493', '2025-12-02', '2026-12-31', 'ok', 'mineur gratuite'],
    ['BALOGH', 'Tibor', 'Tibor.BALOGH@ec.europa.eu', 'LS-0494', '2026-01-13', '2026-12-31', 'ok', ''],
    ['MARQUES E SOUSA', 'Nuno', 'npmesousa@gmail.com', 'LS-0495', '2026-01-30', '2026-12-31', 'ok', ''],
    ['PUIU', 'Stefana', 'stefana.puiu@gmail.com', 'LS-0496', '2026-02-16', '2026-12-31', 'ok', ''],
    ['PUIU', 'Mara', 'mara.puiu@outlook.com', 'LS-0497', '2026-02-16', '2026-12-31', 'ok', 'mineur gratuite'],
    ['DRANCA', 'Florian', 'drunkf@gmail.com', 'LS-0501', '2025-02-24', '2026-12-31', 'ok', ''],
    ['DIMISIANOS', 'Nikolaos', 'nidimus@gmail.com', 'LS-0498', '2025-06-06', '2026-12-31', 'ok', ''],
    ['MAYER', 'Nicolas', 'mayernicolas1@gmail.com', null, '2025-08-25', '2026-12-31', 'non valable', 'manque cases cochées'],
    ['AMER CATA', 'Montserrat', 'Montserrat.Amer_Cata@curia.europa.eu', 'LS-0499', '2025-10-28', '2026-12-31', 'ok', ''],
    ['PIECZKA', 'Stanislaw', 'pieczka.stanislaw4@gmail.com', null, '2025-10-13', '2026-12-31', 'non valable', 'mauvais formulaire'],
    ['SZOSTEK-PIECZKA', 'Anna', 'szostek.annamaria@gmail.com', null, '2025-10-13', '2026-12-31', 'non valable', 'manque cases cochées'],
    ['JUNG', 'Anne-Claire', 'ac.jung345@gmail.com', null, '2025-10-23', '2027-12-31', 'non valable', 'manque adresse'],
    ['LARUELLE', 'Hélène', 'helenelaruelle@hotmail.com', 'LS-0500', '2025-10-25', '2027-12-31', 'ok', ''],
    ['ACCADIA', 'Benjamin', 'baccadia@hotmail.com', 'LS-0502', '2025-11-05', '2027-12-31', 'ok', ''],
    ['ACCADIA', 'Lidija', 'lidija.petrovska@yahoo.com', 'LS-0503', '2025-11-06', '2027-12-31', 'ok', ''],
    ['DE SOTO COBET', 'Ophélie', 'orlanth@pt.lu', 'LS-0504', '2026-01-23', '2027-12-31', 'ok', ''],
    ['BUHRMANN', 'Heino', 'hbuhrmann@web.de', 'LS-0505', '2026-01-19', '2026-12-31', 'ok', ''],
    ['BIGDEL SHAHSAVAN', 'Shadab', 'shbigdel@gmail.com', 'LS-0506', '2026-01-19', '2026-12-31', 'ok', ''],
    ['KUKOVECZ', 'Mate', 'matekukovecz@gmail.com', 'LS-0552', '2025-10-15', '2027-12-31', 'ok', ''],
    ['DACKNER', 'Lynette', 'lynette.dackner@icloud.com', 'LS-0553', '2025-10-22', '2026-12-31', 'ok', ''],
    ['DACKNER', 'Johan', 'dackner@hotmail.com', 'LS-0554', '2025-10-22', '2026-12-31', 'ok', ''],
    ['DIA', 'Melanie', 'melabug25@yahoo.fr', 'LS-0555', '2026-02-27', '2027-12-31', 'ok', ''],
    ['SAMSON', 'Jérôme', 'jerome.samson@gmail.com', 'LS-0596', '2026-03-13', '2026-12-31', 'ok', ''],
    ['BRUZZESE', 'Lina', 'linebruzzese@gmail.com', 'LS-0606', '2026-03-17', '2026-12-31', 'ok', ''],
];

$licCreated = 0;
$licUpdated = 0;
$medUpdated = 0;
$nonValable = 0;

foreach ($data as [$last, $first, $email, $licNo, $certDate, $flassaExpiry, $status, $comment]) {
    $user = User::where('primary_email', $email)->first();
    if (! $user) {
        echo "  ⚠️ Not found: {$first} {$last} ({$email})\n";

        continue;
    }

    // Create/update FLASSA licence
    if ($licNo) {
        $lic = MemberLicence::updateOrCreate(
            ['user_id' => $user->id, 'federation_id' => $flassa->id],
            ['licence_number' => $licNo, 'season' => '2025-2026']
        );
        $lic->wasRecentlyCreated ? $licCreated++ : $licUpdated++;
    }

    // Update medical cert with FLASSA-computed expiry
    $cert = $user->documents()->where('category', 'medical')->where('is_current', true)->first();
    if ($cert) {
        $updates = [
            'date_established' => $certDate,
            'expiry_date' => $flassaExpiry,
            'is_compliant' => $status === 'ok',
        ];
        if ($status !== 'ok') {
            $updates['compliance_notes'] = "FLASSA: non valable — {$comment}";
            $updates['is_verified'] = false;
            $nonValable++;
        } else {
            $updates['compliance_notes'] = "FLASSA licence {$licNo}, valid until {$flassaExpiry}";
            $updates['is_verified'] = true;
        }
        $cert->update($updates);
        $medUpdated++;
    }
}

echo "\n  FLASSA licences created: {$licCreated}\n";
echo "  FLASSA licences updated: {$licUpdated}\n";
echo "  Medical certs updated: {$medUpdated}\n";
echo "  Non valable (flagged): {$nonValable}\n";

// Summary
echo "\n=== Final State ===\n";
echo '  FLASSA licences: '.MemberLicence::where('federation_id', $flassa->id)->count()."\n";
echo '  FFESSM licences: '.MemberLicence::where('federation_id', 1)->count()."\n";
echo '  Valid medical (expiry > now): '.Document::where('category', 'medical')->where('is_current', true)->where('expiry_date', '>', now())->count()."\n";
echo '  Expired medical: '.Document::where('category', 'medical')->where('is_current', true)->where('expiry_date', '<=', now())->count()."\n";
echo '  Non-compliant (flagged): '.Document::where('category', 'medical')->where('is_current', true)->where('is_compliant', false)->count()."\n";
