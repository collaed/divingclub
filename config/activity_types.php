<?php

declare(strict_types=1);

/*
 * Canonical activity / event types with their colours.
 *
 * Single source of truth for:
 *  - the instructor availability planner legend and slots
 *  - the season-planning pattern event-type selectors
 *
 * Colours match the old CEP Google Sheet planning. Keep in sync with the
 * `.activity-{key}` classes in resources/scss/partials/_planning.scss.
 */

return [
    'pool' => ['color' => '#4a86c8', 'text' => '#fff', 'icon' => '🏊', 'label' => 'Pool'],
    'pool_kids' => ['color' => '#2ecc71', 'text' => '#fff', 'icon' => '👶', 'label' => '↳ Kids'],
    'pool_pn1' => ['color' => '#1a237e', 'text' => '#fff', 'icon' => '1️⃣', 'label' => '↳ PN1'],
    'pool_pn23' => ['color' => '#e74c3c', 'text' => '#fff', 'icon' => '🔴', 'label' => '↳ PN2+'],
    'pool_swimming' => ['color' => '#ff9800', 'text' => '#fff', 'icon' => '🏊‍♂️', 'label' => '↳ Swimming'],
    'training' => ['color' => '#5c6bc0', 'text' => '#fff', 'icon' => '🤿', 'label' => 'Training'],
    'apnea' => ['color' => '#00c853', 'text' => '#000', 'icon' => '🫁', 'label' => 'Apnea'],
    'fosse' => ['color' => '#00695c', 'text' => '#fff', 'icon' => '🕳️', 'label' => 'Fosse'],
    'quarry' => ['color' => '#00bcd4', 'text' => '#000', 'icon' => '🪨', 'label' => 'Quarry/Lake'],
    'long_trip' => ['color' => '#f9a825', 'text' => '#000', 'icon' => '✈️', 'label' => 'Long Trip'],
    'theory' => ['color' => '#78909c', 'text' => '#fff', 'icon' => '📖', 'label' => 'Theory'],
    'social' => ['color' => '#e91e63', 'text' => '#fff', 'icon' => '🎉', 'label' => 'Social'],
    'closed' => ['color' => '#9e9e9e', 'text' => '#fff', 'icon' => '🚫', 'label' => 'Closed'],
];
