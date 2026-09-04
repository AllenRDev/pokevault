<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CardController;

Route::get('/cards', function () {
    return Inertia::render('cards/index');
})->name('cards.index');

Route::get('/cards/{id}', function (string $id) {
    return Inertia::render('cards/show', [
        'card' => app(\App\Services\TcgDexService::class)->getCard($id),
    ]);
})->name('cards.show');

Route::get('/cards/{id}', [CardController::class, 'page'])
    ->name('cards.show');

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
