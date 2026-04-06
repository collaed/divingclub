<?php

/**
 * Demo scenario seeder — creates 20 realistic events with registrations,
 * payments, external registrations, dive groups, communications, and photos.
 *
 * Run: php artisan tinker < database/seeders/demo_scenario.php
 */

use App\Models\ClubPartnership;
use App\Models\DiveGroup;
use App\Models\DiveGroupMember;
use App\Models\EmailLog;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\EventRegistration;
use App\Models\ExternalRegistration;
use App\Models\PaymentExpected;
use App\Models\User;
use Illuminate\Support\Str;

echo "🎬 Creating demo scenario...\n\n";

// Get real members
$members = User::whereHas('detail')->limit(80)->get();
$bureau = User::role(['bureau_master', 'bureau_technical'])->get();
$instructors = User::role('instructor')->limit(8)->get();
$bureauId = $bureau->first()->id;

// ── 20 Events ──
$eventDefs = [
    ['Piscine Merl', 'pool', 7, '19:00', '20:30', 'Piscine Merl, Luxembourg', 20, 0, null],
    ['Apnée', 'pool', 3, '18:30', '20:00', 'Forum Geesseknapchen', 12, 0, null],
    ['Piscine Steinfort', 'pool', 10, '19:30', '21:00', 'Piscine Steinfort', 16, 0, null],
    ['Fosse Nemo 33', 'dive', 14, '09:00', '16:00', 'Nemo 33, Bruxelles', 12, 25.00, null],
    ['Théorie : Tables MN90', 'theory', 8, '19:00', '21:00', 'Salle CEP, Luxembourg', 30, 0, null],
    ['Sortie Gravière du Fort', 'dive', 19, '06:30', '17:00', 'Gravière du Fort, Holtzheim', 20, 15.00, 4],
    ['Sortie Vodelée', 'dive', 26, '07:00', '17:00', 'Lac de Vodelée, Belgique', 16, 20.00, 2],
    ['Apnée', 'pool', 10, '18:30', '20:00', 'Forum Geesseknapchen', 12, 0, null],
    ['Piscine Merl', 'pool', 14, '19:00', '20:30', 'Piscine Merl, Luxembourg', 20, 0, null],
    ['Formation Apnée', 'theory', 17, '19:00', '21:00', 'Salle CEP, Luxembourg', 20, 0, null],
    ['Sortie Barges', 'dive', 33, '07:00', '18:00', 'Lac de Barges, Belgique', 14, 25.00, 2],
    ['COSL Spillfest', 'social', 40, '18:00', '23:00', 'Coque, Luxembourg', 50, 15.00, null],
    ['Juan-les-Pins', 'trip', 50, '06:00', '22:00', 'Juan-les-Pins, France', 20, 450.00, null],
    ['Piscine Merl', 'pool', 21, '19:00', '20:30', 'Piscine Merl, Luxembourg', 20, 0, null],
    ['Apnée', 'pool', 17, '18:30', '20:00', 'Forum Geesseknapchen', 12, 0, null],
    ['Théorie : Décompression', 'theory', 22, '19:00', '21:00', 'Salle CEP, Luxembourg', 30, 0, null],
    ['Piscine Steinfort', 'pool', 24, '19:30', '21:00', 'Piscine Steinfort', 16, 0, null],
    ['Sortie Gravière Robertsau', 'dive', 40, '07:00', '16:00', 'Gravière Robertsau, Strasbourg', 16, 15.00, 3],
    ['BBQ de fin de saison', 'social', 60, '12:00', '22:00', 'Entrepôt CEP, Berchem', 60, 10.00, null],
    ['Oman — Musandam', 'trip', 90, '06:00', '22:00', 'Musandam, Oman', 12, 1800.00, null],
];

$events = collect();
foreach ($eventDefs as [$title, $type, $daysAhead, $start, $end, $loc, $max, $cost, $extSlots]) {
    $ev = Event::create([
        'title' => $title,
        'event_type' => $type,
        'event_date' => now()->addDays($daysAhead)->toDateString(),
        'event_time' => $start,
        'end_time' => $end,
        'location' => $loc,
        'max_participants' => $max,
        'estimated_cost' => $cost > 0 ? $cost : null,
        'is_federated' => $extSlots > 0,
        'external_slots' => $extSlots ?? 0,
        'status' => 'published',
        'created_by' => $bureauId,
        'description' => "Événement organisé par le CEP. Inscription obligatoire.",
    ]);
    $events->push($ev);
    echo "  📅 {$ev->title} ({$ev->event_date})\n";
}

// ── Registrations (5-15 per event, varied) ──
echo "\n👥 Registering members...\n";
$regCounts = [12, 8, 10, 11, 15, 18, 14, 6, 13, 9, 12, 25, 18, 11, 7, 14, 9, 13, 35, 10];

foreach ($events as $i => $ev) {
    $count = min($regCounts[$i], $members->count());
    $selected = $members->random($count);

    foreach ($selected as $j => $user) {
        $status = $j < $ev->max_participants ? 'registered' : 'waiting';
        EventRegistration::create([
            'event_id' => $ev->id,
            'user_id' => $user->id,
            'status' => $status,
            'waiting_list_position' => $status === 'waiting' ? $j - $ev->max_participants + 1 : null,
            'registered_by' => $user->id,
        ]);
    }
    echo "  {$ev->title}: {$count} registrations\n";
}

// ── Payment records for paid events ──
echo "\n💰 Creating payment records...\n";
foreach ($events as $ev) {
    if (! $ev->estimated_cost || $ev->estimated_cost <= 0) {
        continue;
    }

    $regs = EventRegistration::where('event_id', $ev->id)->where('status', 'registered')->with('user')->get();
    foreach ($regs as $reg) {
        $comm = 'CEP-' . strtoupper(Str::random(4)) . '-' . $reg->user_id;
        $isPaid = rand(0, 100) < 40; // 40% already paid

        PaymentExpected::create([
            'user_id' => $reg->user_id,
            'type' => 'event',
            'event_id' => $ev->id,
            'amount_due' => $ev->estimated_cost,
            'communication' => $comm,
            'status' => $isPaid ? 'paid' : 'pending',
            'amount_paid' => $isPaid ? $ev->estimated_cost : 0,
            'paid_at' => $isPaid ? now()->subDays(rand(1, 10))->toDateString() : null,
        ]);
    }
    echo "  {$ev->title}: {$regs->count()} payment records (€{$ev->estimated_cost} each)\n";
}

// ── Deposit schedule for trips ──
echo "\n📋 Adding deposit schedules for trips...\n";
foreach ($events->whereIn('event_type', ['trip']) as $ev) {
    $ev->update([
        'deposit_1_date' => now()->addDays(7)->toDateString(),
        'deposit_1_amount' => round($ev->estimated_cost * 0.3, 2),
        'deposit_2_date' => now()->addDays(30)->toDateString(),
        'deposit_2_amount' => round($ev->estimated_cost * 0.3, 2),
        'deposit_3_date' => now()->addDays(60)->toDateString(),
        'deposit_3_amount' => round($ev->estimated_cost * 0.4, 2),
    ]);
    echo "  {$ev->title}: 3 deposits (30%/30%/40% of €{$ev->estimated_cost})\n";
}

// ── External registrations via partnership ──
echo "\n🤝 Creating external registrations...\n";
$partnership = ClubPartnership::first();
if ($partnership) {
    $fedEvents = $events->where('is_federated', true);
    $extMembers = [
        ['Hans Müller', 'hans.mueller@example.de', 'CMAS 2★', '2026-12-31', 'German speaker, own equipment'],
        ['Sophie Dupont', 'sophie.dupont@example.fr', 'FFESSM N2', '2026-09-15', 'Needs 12L tank'],
        ['Marco Rossi', 'marco.rossi@example.it', 'PADI AOW', '2026-11-30', 'Speaks Italian and English'],
        ['Anna Kowalski', 'anna.k@example.pl', 'CMAS 1★', '2027-01-20', 'First open water dive'],
    ];

    $extIdx = 0;
    foreach ($fedEvents as $ev) {
        for ($k = 0; $k < min(2, count($extMembers) - $extIdx); $k++) {
            [$name, $email, $cert, $med, $notes] = $extMembers[$extIdx];
            ExternalRegistration::create([
                'event_id' => $ev->id,
                'partnership_id' => $partnership->id,
                'external_member_name' => $name,
                'external_member_email' => $email,
                'external_cert_level' => $cert,
                'external_medical_valid_until' => $med,
                'notes' => $notes,
                'status' => $k === 0 ? 'approved' : 'pending',
            ]);
            echo "  {$name} → {$ev->title} (" . ($k === 0 ? 'approved' : 'pending') . ")\n";
            $extIdx++;
        }
    }
}

// ── Communications (email log entries for events) ──
echo "\n📧 Creating event communications...\n";
$commEvents = $events->take(6);
foreach ($commEvents as $ev) {
    $regs = EventRegistration::where('event_id', $ev->id)->where('status', 'registered')->with('user')->limit(5)->get();
    foreach ($regs as $reg) {
        EmailLog::create([
            'event_id' => $ev->id,
            'user_id' => $reg->user_id,
            'to_email' => $reg->user->primary_email,
            'from_name' => 'CEP Bureau',
            'from_email' => 'clubcep@clubcep.eu',
            'subject' => "Rappel: {$ev->title} le " . $ev->event_date->format('d/m'),
            'body' => "Bonjour {$reg->user->detail?->first_name},\n\nRappel pour {$ev->title} le {$ev->event_date->format('d/m/Y')}.\nLieu: {$ev->location}\n\nÀ bientôt!\nLe Bureau CEP",
            'status' => 'sent',
            'direction' => 'outbound',
        ]);
    }
    echo "  {$ev->title}: {$regs->count()} reminder emails\n";
}

// ── Dive groups for dive events ──
echo "\n🤿 Creating dive groups...\n";
$diveEvents = $events->where('event_type', 'dive')->take(4);
foreach ($diveEvents as $ev) {
    $regs = EventRegistration::where('event_id', $ev->id)->where('status', 'registered')->pluck('user_id');
    if ($regs->count() < 4) {
        continue;
    }

    // Create 2-3 groups per event
    $groupCount = min(3, intdiv($regs->count(), 2));
    $chunks = $regs->shuffle()->chunk(ceil($regs->count() / $groupCount));

    foreach ($chunks as $gi => $chunk) {
        $group = DiveGroup::create([
            'event_id' => $ev->id,
            'name' => 'Palanquée ' . ($gi + 1),
            'dive_mode' => 'exploration',
            'planned_depth' => rand(15, 35),
            'planned_duration' => rand(30, 50),
            'gas_mix' => 'Air',
            'line_number' => $gi + 1,
            'planned_entry_time' => '09:' . str_pad($gi * 15, 2, '0', STR_PAD_LEFT),
            'created_by' => $bureauId,
        ]);

        foreach ($chunk as $j => $userId) {
            DiveGroupMember::create([
                'dive_group_id' => $group->id,
                'user_id' => $userId,
                'role' => $j === 0 ? 'leader' : 'diver',
            ]);
        }
        echo "  {$ev->title} → {$group->name}: {$chunk->count()} divers\n";
    }
}

// ── Photos for recent/past-like events (simulate uploads) ──
echo "\n📸 Assigning photos to events...\n";
$existingPhotos = EventPhoto::where('approved', true)->limit(40)->get();
$photoEvents = $events->take(8);
foreach ($photoEvents as $ev) {
    $batch = $existingPhotos->random(min(5, $existingPhotos->count()));
    foreach ($batch as $photo) {
        // Create new photo records linked to this event (reuse paths)
        EventPhoto::create([
            'event_id' => $ev->id,
            'uploaded_by' => $members->random()->id,
            'path' => $photo->path,
            'thumbnail_path' => $photo->thumbnail_path,
            'quality_score' => rand(70, 98),
            'has_faces' => (bool) rand(0, 1),
            'approved' => true,
            'gdpr_consent' => true,
        ]);
    }
    echo "  {$ev->title}: {$batch->count()} photos\n";
}

// ── Summary ──
echo "\n✅ Demo scenario complete!\n";
echo "  Events created: {$events->count()}\n";
echo "  Registrations: " . EventRegistration::whereIn('event_id', $events->pluck('id'))->count() . "\n";
echo "  Payments: " . PaymentExpected::whereIn('event_id', $events->pluck('id'))->count() . "\n";
echo "  External regs: " . ExternalRegistration::whereIn('event_id', $events->pluck('id'))->count() . "\n";
echo "  Dive groups: " . DiveGroup::whereIn('event_id', $events->pluck('id'))->count() . "\n";
echo "  Communications: " . EmailLog::whereIn('event_id', $events->pluck('id'))->count() . "\n";
echo "  Photos: " . EventPhoto::whereIn('event_id', $events->pluck('id'))->count() . "\n";
