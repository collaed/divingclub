<?php

/**
 * User Manual Demo — Sets up a complete 2025-2026 season with realistic data.
 *
 * Run: php artisan tinker --execute 'require "database/seeders/manual_demo.php";'
 */

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\InstructorAvailability;
use App\Models\Season;
use App\Models\SeasonHoliday;
use App\Models\SeasonPattern;
use App\Models\User;
use Carbon\Carbon;

echo "📖 Setting up User Manual Demo...\n\n";

// ── Chapter 1: Season Setup ──
echo "═══ Chapter 1: Season Setup ═══\n";

$season = Season::updateOrCreate(
    ['year' => 2025],
    [
        'name' => 'Saison 2025-2026',
        'start_date' => '2025-09-15',
        'end_date' => '2026-07-15',
        'is_active' => true,
    ]
);
echo "  ✅ Season: {$season->name}\n";

// Luxembourg school holidays 2025-2026
$holidays = [
    ['Toussaint', '2025-11-01', '2025-11-09'],
    ['Noël', '2025-12-20', '2026-01-04'],
    ['Carnaval', '2026-02-14', '2026-02-22'],
    ['Pâques', '2026-03-28', '2026-04-12'],
    ['Ascension', '2026-05-14', '2026-05-14'],
    ['Pentecôte', '2026-05-23', '2026-05-31'],
    ['Fête nationale', '2026-06-23', '2026-06-23'],
];

SeasonHoliday::where('season_id', $season->id)->delete();
foreach ($holidays as [$name, $start, $end]) {
    SeasonHoliday::create([
        'season_id' => $season->id,
        'name' => $name,
        'start_date' => $start,
        'end_date' => $end,
    ]);
    echo "  🏖️ {$name}: {$start} → {$end}\n";
}

// Recurring patterns
SeasonPattern::where('season_id', $season->id)->delete();
$patterns = [
    [1, '19:00', '21:00', 'pool', 'Piscine Steinfort', 'Piscine Steinfort', 16],
    [3, '17:20', '20:00', 'pool', 'Piscine Geesseknäppchen', 'Forum Geesseknäppchen', 20],
    [5, '18:30', '20:00', 'pool', 'Apnée', 'Forum Geesseknäppchen', 12],
];
foreach ($patterns as [$dow, $start, $end, $type, $title, $loc, $max]) {
    SeasonPattern::create([
        'season_id' => $season->id,
        'day_of_week' => $dow,
        'start_time' => $start,
        'end_time' => $end,
        'event_type' => $type,
        'title' => $title,
        'location' => $loc,
        'max_participants' => $max,
    ]);
    $dayName = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$dow];
    echo "  📅 {$dayName}: {$title} ({$start}-{$end}) @ {$loc}\n";
}

// ── Generate events from patterns (April-June 2026) ──
echo "\n═══ Chapter 2: Calendar Generation ═══\n";

$holidayRanges = SeasonHoliday::where('season_id', $season->id)->get();
$genStart = Carbon::parse('2026-04-13'); // after Easter
$genEnd = Carbon::parse('2026-07-15');
$patternsAll = SeasonPattern::where('season_id', $season->id)->get();
$bureauId = User::role('bureau_master')->first()->id;
$generated = 0;

$cursor = $genStart->copy();
while ($cursor->lte($genEnd)) {
    // Check if in holiday
    $inHoliday = $holidayRanges->contains(fn ($h) => $cursor->between($h->start_date, $h->end_date));

    if (! $inHoliday) {
        foreach ($patternsAll as $pat) {
            if ($cursor->dayOfWeekIso === $pat->day_of_week) {
                // Skip if event already exists on this date with same title
                $exists = Event::where('event_date', $cursor->toDateString())
                    ->where('title', $pat->title)->exists();
                if (! $exists) {
                    Event::create([
                        'title' => $pat->title,
                        'event_type' => $pat->event_type,
                        'event_date' => $cursor->toDateString(),
                        'event_time' => $pat->start_time,
                        'end_time' => $pat->end_time,
                        'location' => $pat->location,
                        'max_participants' => $pat->max_participants,
                        'status' => 'published',
                        'season_id' => $season->id,
                        'created_by' => $bureauId,
                    ]);
                    $generated++;
                }
            }
        }
    }
    $cursor->addDay();
}
echo "  Generated {$generated} events from patterns\n";

// Add monthly Fosse events (Thu or Fri)
$fosseMonths = ['2026-04-16', '2026-05-15', '2026-06-19'];
foreach ($fosseMonths as $date) {
    $exists = Event::where('event_date', $date)->where('title', 'like', '%Fosse%')->exists();
    if (! $exists) {
        Event::create([
            'title' => 'Fosse',
            'event_type' => 'pool',
            'event_date' => $date,
            'event_time' => '19:00',
            'end_time' => '21:00',
            'location' => 'Nemo 33, Bruxelles',
            'max_participants' => 12,
            'estimated_cost' => 25.00,
            'status' => 'published',
            'season_id' => $season->id,
            'created_by' => $bureauId,
        ]);
        echo "  🕳️ Fosse: {$date}\n";
    }
}

// ── Chapter 3: Instructor Availability ──
echo "\n═══ Chapter 3: Instructor Availability ═══\n";

$instructors = User::role(['instructor', 'bureau_master', 'bureau_technical'])->limit(10)->get();
$upcomingEvents = Event::where('event_date', '>=', '2026-04-06')
    ->where('event_date', '<=', '2026-05-31')
    ->where('status', 'published')
    ->orderBy('event_date')
    ->get();

$withInstructor = 0;
$withoutInstructor = 0;

foreach ($upcomingEvents as $ev) {
    // 80% chance an instructor is available
    if (rand(1, 100) <= 80) {
        $inst = $instructors->random(rand(1, min(3, $instructors->count())));
        foreach ($inst as $i) {
            InstructorAvailability::updateOrCreate([
                'user_id' => $i->id,
                'date' => $ev->event_date,
                'slot' => 'evening',
                'activity_type' => $ev->event_type,
            ], [
                'event_id' => $ev->id,
            ]);
        }
        $withInstructor++;
    } else {
        $withoutInstructor++;
    }
}
echo "  ✅ {$withInstructor} events with instructor availability\n";
echo "  ⚠️ {$withoutInstructor} events WITHOUT instructor — may need cancellation\n";

// ── Chapter 4: Registrations ──
echo "\n═══ Chapter 4: Event Registrations ═══\n";

$members = User::whereHas('detail')->limit(60)->get();
$regEvents = Event::where('event_date', '>=', '2026-04-13')
    ->where('event_date', '<=', '2026-05-15')
    ->where('status', 'published')
    ->orderBy('event_date')
    ->limit(20)
    ->get();

$totalRegs = 0;
foreach ($regEvents as $ev) {
    $count = rand(4, min(15, $ev->max_participants + 3)); // some overflow for waiting list
    $selected = $members->random(min($count, $members->count()));

    foreach ($selected as $j => $user) {
        $exists = EventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->exists();
        if ($exists) {
            continue;
        }

        $status = $j < $ev->max_participants ? 'registered' : 'waiting';
        EventRegistration::create([
            'event_id' => $ev->id,
            'user_id' => $user->id,
            'status' => $status,
            'waiting_list_position' => $status === 'waiting' ? $j - $ev->max_participants + 1 : null,
            'registered_by' => $user->id,
        ]);
        $totalRegs++;
    }
}
echo "  ✅ {$totalRegs} registrations across {$regEvents->count()} events\n";

echo "\n✅ Manual demo setup complete!\n";
