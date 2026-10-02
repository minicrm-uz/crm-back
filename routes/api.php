<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LeadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn (Request $request) => response()->json([
    'ok' => true,
    'time' => now()->toIso8601String(),
]));

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::middleware('auth.jwt')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::prefix('leads')->group(function () {
    Route::get('/', [LeadController::class, 'index']);
    Route::post('/', [LeadController::class, 'store']);
    Route::get('/{lead}', [LeadController::class, 'show']);
    Route::patch('/{lead}', [LeadController::class, 'update']);
    Route::patch('/{lead}/status', [LeadController::class, 'updateStatus']);
    Route::delete('/{lead}', [LeadController::class, 'destroy']);
    Route::get('/{lead}/activities', [LeadController::class, 'activities']);
});
