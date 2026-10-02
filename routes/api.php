<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn (Request $request) => response()->json([
    'ok' => true,
    'time' => now()->toIso8601String(),
]));
