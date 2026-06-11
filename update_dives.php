<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$event = App\Models\Event::find(4158);
$event->update(['dive_unit_price' => 35, 'nitrox_supplement' => 10]);
echo "Set dive_unit_price=35, nitrox_supplement=10\n";

$dives = [
    ['Aurel', 'BONTEA', 10, 0],
    ['Jerome', 'TONGIO', 10, 0],
    ['Jules', 'TONGIO', 9, 0],
    ['Pietro', 'GIANCOLA', 10, 0],
    ['Vesa', 'TANNER', 8, 0],
    ['Michel', 'BROCHARD', 10, 0],
    ['Roger', 'KRAEMER', 2, 0],
    ['Manuel', 'MONTEIRO', 6, 0],
    ['Ricardo', 'SELVES', 5, 0],
    ['Keran', 'CHAUSSARD', 10, 10],
    ['Florian', 'DRANCA', 9, 5],
    ['Martine', 'SONDT', 6, 6],
    ['Ionas', 'MICHAILIDIS', 10, 6],
    ['Nikolaos', 'DIMISIANOS', 9, 8],
    ['Eduardo', 'HERAS', 8, 6],
    ['Lilian', 'GODFRIN', 7, 7],
    ['Eddy', 'COLLART', 10, 1],
    ['Mafalda', 'COLLART', 6, 6],
    ['Marco', 'MARCOS', 6, 1],
    ['Lina', 'BRUZZESE', 5, 0],
    ['Val', 'KRAEMER', 2, 0],
    ['Fr', 'VIGNERON', 6, 6],
    ['Bruno', 'BAUMLEN', 8, 8],
];

$updated = 0;
foreach ($dives as [$first, $last, $numDives, $numNitrox]) {
    $user = App\Models\User::whereHas('detail', fn ($q) => $q->where('last_name', $last)->where('first_name', 'like', $first . '%'))->first();
    if (! $user) {
        echo "NOT FOUND: $first $last\n";
        continue;
    }
    $receipt = App\Models\TripReceipt::where('event_id', 4158)
        ->where('user_id', $user->id)
        ->where('category', 'individual')
        ->where('description', 'like', '%Dive%')
        ->first();
    if (! $receipt) {
        echo "NO RECEIPT: $first $last\n";
        continue;
    }
    $receipt->update(['description' => $numDives . ' dives + ' . $numNitrox . ' nitrox']);
    $updated++;
}
echo "Updated: $updated receipts\n";
