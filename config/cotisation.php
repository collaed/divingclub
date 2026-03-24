<?php

/**
 * Cotisation (membership fee) schedule — updated yearly by the bureau.
 *
 * @author ClubCEP.eu
 */

return [
    'year' => '2026',
    'reduced_after' => '2026-04-01', // half-season rate after this date

    // Bank details
    'iban' => 'LU21 0019 7855 8919 6000',
    'bic' => 'BCEELULL',
    'bank' => 'BCEE',
    'beneficiary' => 'CLUB EUROPEEN DE PLONGEE',

    // CEP membership fees
    'cep' => [
        'fonctionnaire' => ['label' => 'Cotisation CEP fonctionnaire (18+)', 'amount' => 105, 'reduced' => 55],
        'externe' => ['label' => 'Cotisation CEP externe (18+)', 'amount' => 110, 'reduced' => 55],
        'jeune16' => ['label' => 'Cotisation jeune (16-17 ans)', 'amount' => 55, 'reduced' => 25],
        'jeune' => ['label' => 'Cotisation jeune (12-15 ans)', 'amount' => 55, 'reduced' => 25],
        'enfant' => ['label' => 'Cotisation enfant (<12 ans)', 'amount' => 55, 'reduced' => 25],
        'sympathisant' => ['label' => 'Cotisation sympathisant', 'amount' => 30, 'reduced' => 30],
    ],

    // FFESSM licence fees (auto-selected by age category)
    'licence' => [
        'adulte' => ['label' => 'Licence FFESSM adulte', 'amount' => 48.50, 'for' => ['fonctionnaire', 'externe', 'jeune16']],
        'jeune' => ['label' => 'Licence FFESSM jeune (12-15)', 'amount' => 30.50, 'for' => ['jeune']],
        'enfant' => ['label' => 'Licence FFESSM enfant', 'amount' => 14.00, 'for' => ['enfant']],
        'none' => ['label' => 'Pas de licence (sympathisant)', 'amount' => 0, 'for' => ['sympathisant']],
    ],

    // Individual insurance options
    'insurance' => [
        'loisir1' => ['label' => 'Assurance Loisir 1', 'amount' => 24.20],
        'loisir1top' => ['label' => 'Assurance Loisir 1 Top', 'amount' => 46.35],
        'loisir2' => ['label' => 'Assurance Loisir 2', 'amount' => 28.85],
        'loisir2top' => ['label' => 'Assurance Loisir 2 Top', 'amount' => 57.70],
        'loisir3' => ['label' => 'Assurance Loisir 3', 'amount' => 49.45],
        'loisir3top' => ['label' => 'Assurance Loisir 3 Top', 'amount' => 95.80],
        'none' => ['label' => "Pas d'assurance individuelle", 'amount' => 0],
    ],
];
