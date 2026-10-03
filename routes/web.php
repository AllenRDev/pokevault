<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserCardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/cards', function () {
    return Inertia::render('cards/index');
})->name('cards.index');

/*
 * Registered before "/cards/{id}" so the literal segment wins over
 * the wildcard parameter.
 */
Route::get('/cards/scan', function () {
    return Inertia::render('cards/scan');
})->middleware(['auth'])->name('cards.scan');

Route::get('/cards/{id}', [CardController::class, 'page'])->name('cards.show');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/collection', function () {
        return Inertia::render('collection/index');
    })->name('collection.index');

    Route::get('/collection/cards', [UserCardController::class, 'index'])
        ->name('collection.cards.index');

    Route::post('/collection/cards', [UserCardController::class, 'store'])
        ->name('collection.cards.store');

    Route::put('/collection/cards/{userCard}', [UserCardController::class, 'update'])
        ->name('collection.cards.update');

    Route::delete('/collection/cards/{userCard}', [UserCardController::class, 'destroy'])
        ->name('collection.cards.destroy');
});

require __DIR__.'/settings.php';
