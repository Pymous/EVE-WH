<?php

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Api\PatientController;

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

Route::get('/item/search', [ApiController::class, 'searchItem']);
Route::get('/items/search', [ApiController::class, 'searchItems']);
Route::get('/items/ores', [ApiController::class, 'getOresList']);

Route::get('/solver/ores', [ApiController::class, 'solverOres']);
