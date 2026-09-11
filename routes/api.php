<?php

use Illuminate\Http\Request;
use App\Http\Controllers\CardController;
use App\Http\Controllers\UserCardController;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/cards/search', [CardController::class, 'search']);
Route::get('/cards/{id}', [CardController::class, 'show']);