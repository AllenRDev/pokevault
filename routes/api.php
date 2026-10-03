<?php

use App\Http\Controllers\CardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/cards/search', [CardController::class, 'search']);

/*
 * Registered before "/cards/{id}" so the literal segment wins over the
 * wildcard parameter, the same reason "/cards/scan" comes first in web.php.
 */
Route::get('/cards/match', [CardController::class, 'match']);

Route::get('/cards/{id}', [CardController::class, 'show']);
