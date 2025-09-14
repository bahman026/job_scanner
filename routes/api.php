<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JobSearchController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Job search API endpoint (no CSRF protection, no throttling for long searches)
Route::post('/search', [JobSearchController::class, 'search']);

// Test endpoint without any middleware
Route::post('/test', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Test endpoint working',
        'timestamp' => now()
    ]);
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'service' => 'Job Scanner API'
    ]);
});
