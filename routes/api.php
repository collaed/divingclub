<?php

use App\Http\Controllers\Api\FederationApiController;
use Illuminate\Support\Facades\Route;

// Inter-club federation API (authenticated via X-Club-Key-Id / X-Club-Secret headers)
Route::prefix('federation')->group(function () {
    Route::get('/events', [FederationApiController::class, 'events']);
    Route::post('/register', [FederationApiController::class, 'register']);
    Route::delete('/register/{id}', [FederationApiController::class, 'cancel']);
});

// Developer instance check API (signed with developer private key)
Route::get('/instance/status', function (\Illuminate\Http\Request $request) {
    $signature = $request->header('X-Dev-Signature');
    $timestamp = $request->header('X-Dev-Timestamp');
    abort_unless($signature && $timestamp, 403);
    abort_unless(abs(time() - (int) $timestamp) < 300, 403, 'Timestamp expired');

    $pubKey = config('app.dev_public_key');
    abort_unless($pubKey, 404);

    $valid = openssl_verify($timestamp, base64_decode($signature), $pubKey, OPENSSL_ALGO_SHA256);
    abort_unless($valid === 1, 403, 'Invalid signature');

    $admin = \App\Models\User::whereHas('role', fn($q) => $q->where('slug', 'bureau_master'))->first();
    return response()->json([
        'version' => config('app.version', '1.0.0'),
        'club_name' => config('app.name'),
        'admin_name' => $admin?->name,
        'admin_email' => $admin?->primary_email,
        'member_count' => \App\Models\User::count(),
        'active_member_count' => \App\Models\User::whereHas('status', fn($q) => $q->where('slug', 'actif'))->count(),
    ]);
});
