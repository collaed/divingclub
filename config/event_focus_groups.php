<?php

declare(strict_types=1);

/*
 * Equipment-priority focus markers for an event — orthogonal to the
 * activity type in config/activity_types.php (any event can carry one, or
 * none). Rendered as a diagonal hatch mixed into the event's own colour
 * rather than a colour of its own (see .focus-* in _planning.scss).
 * Colours deliberately reuse the matching pool_* accents from
 * activity_types.php so the same focus reads the same way everywhere on
 * the calendar.
 */

return [
    'kids' => ['color' => '#2ecc71', 'icon' => '👶', 'label' => 'Kids'],
    'pn1' => ['color' => '#1a237e', 'icon' => '1️⃣', 'label' => 'PN1'],
    'pn2' => ['color' => '#e74c3c', 'icon' => '🔴', 'label' => 'PN2'],
];
