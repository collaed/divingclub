<?php

use App\Http\Controllers\Api\FederationApiController;
use Illuminate\Support\Facades\Route;

// Inter-club federation API (authenticated via X-Club-Key-Id / X-Club-Secret headers)
Route::prefix('federation')->group(function () {
    Route::get('/events', [FederationApiController::class, 'events']);
    Route::post('/register', [FederationApiController::class, 'register']);
    Route::delete('/register/{id}', [FederationApiController::class, 'cancel']);
});
