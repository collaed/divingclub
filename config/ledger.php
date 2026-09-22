<?php

declare(strict_types=1);

/**
 * The club's own accounting categories ("Rubriques"), the classification
 * rules that turn a bank line's text into a category + fixed tag, and the
 * group aliases that suggest which operation (trip, recurring event) a line
 * belongs to. See LedgerClassificationService.
 */
return [

    // From "Rubriques - pas toucher" in the club's own Tableau Compta workbooks (2020-2022).
    'categories' => [
        '21' => 'Cotisations',
        '22' => "Droits d'entraînement",
        '23' => 'Divers (licences)',
        '24' => 'Sorties club',
        '31' => 'Recettes (remboursements)',
        '32' => 'Dons',
        '33' => 'Divers (intérêts bancaires)',
        '34' => 'Subsides',
        '41' => 'Subsides',
        '42' => 'Subventions coupes et chall.',
        '43' => 'Allocations spéciales',
        '51' => 'Achats et entretiens',
        '52' => 'Locations (hors Ecole Europ.)',
        '53' => 'Divers (à préciser)',
        '61' => 'Moniteurs / entraineurs',
        '62' => 'Equipements',
        '63' => 'Divers (remboursements cotisation)',
        '71' => 'Locations',
        '72' => 'Matériel',
        '73' => 'Arbitres',
        '74' => 'Transports, déplacements',
        '75' => 'Divers (à préciser)',
        '81' => 'Licences, cotisations',
        '82' => 'Assurances',
        '83' => 'Assemblée générale',
        '84' => 'Réceptions',
        '85' => 'Frais de gestion',
        '86' => 'Sorties club',
    ],

    /**
     * Ordered keyword rules against a transaction's description + communication.
     * First match wins. 'direction' is 'in' (amount > 0), 'out' (amount < 0) or
     * 'any'. Each rule's 'tag' must be a fixed tag slug in LedgerTagSeeder.
     */
    'rules' => [
        ['tag' => 'bank_fee', 'category' => '85', 'direction' => 'any', 'pattern' => '/frais de tenue|frais de virement/i'],
        ['tag' => 'subsidy', 'category' => '34', 'direction' => 'in', 'pattern' => '/subside/i'],
        ['tag' => 'cotisation', 'category' => '21', 'direction' => 'in', 'pattern' => '/cotisation|adhesion|adhésion/i'],
        ['tag' => 'course', 'category' => '23', 'direction' => 'in', 'pattern' => '/nitrox|carnet de plong|combinaison/i'],
        ['tag' => 'deposit', 'category' => '24', 'direction' => 'in', 'pattern' => '/acompte|accompte/i'],
        ['tag' => 'trip_balance', 'category' => '86', 'direction' => 'out', 'pattern' => '/solde sortie|solde juan|remboursement acompte|remboursement todi/i'],
        ['tag' => 'extras', 'category' => '24', 'direction' => 'in', 'pattern' => '/boisson|repas|caf[ée]s?|consommation|nemo|rochefontaine|todi/i'],
        ['tag' => 'advance', 'category' => '63', 'direction' => 'out', 'pattern' => '/remboursement (retrait|facture|collation|fact)|carburant/i'],
        ['tag' => 'gonflage', 'category' => '51', 'direction' => 'out', 'pattern' => '/gonflage/i'],
        ['tag' => 'gear', 'category' => '51', 'direction' => 'out', 'pattern' => '/compresseur|kompresseur|sonde|nordparts|techduik|lorraine nautisme/i'],
        ['tag' => 'pool_rental', 'category' => '52', 'direction' => 'out', 'pattern' => '/steinfort/i'],
        ['tag' => 'insurance', 'category' => '82', 'direction' => 'out', 'pattern' => '/lafont|bordereau/i'],
        ['tag' => 'federation', 'category' => '81', 'direction' => 'out', 'pattern' => '/comite regional|comité régional|federation luxembourgeoise|fédération luxembourgeoise|ffessm|flassa|graviere|gravière/i'],
        ['tag' => 'transport', 'category' => '86', 'direction' => 'out', 'pattern' => '/luxair|weber|voyages/i'],
        ['tag' => 'fine', 'category' => '86', 'direction' => 'any', 'pattern' => '/amende/i'],
    ],

    // Name-only suggestion for which operation (trip, recurring event) a line belongs to.
    'groups' => [
        'Juan-les-Pins' => '/juan|jlp|juans|caravelle|easy dive|claj/i',
        'Cap Vert' => '/cap ?vert|cabo verde|luxair/i',
        'Todi' => '/todi|amende/i',
        'Nemo33' => '/nemo/i',
        'Rochefontaine' => '/rochefontaine/i',
        'Oman' => '/\boman\b/i',
    ],
];
