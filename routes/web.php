<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JobSearchController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [JobSearchController::class, 'index'])->name('job-search');
// Removed web search route to prevent conflicts with API route
