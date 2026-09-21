<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\UserCardController;

Route::get('/cards', function () {
    return Inertia::render('cards/index');
})->name('cards.index');

Route::get('/cards/scan', function () {
    return Inertia::render('cards/scan');
})->middleware(['auth'])->name('cards.scan');


Route::get('/cards/{id}', function (string $id) {
    return Inertia::render('cards/show', [
        'card' => app(\App\Services\TcgDexService::class)->getCard($id),
    ]);
})->name('cards.show');

Route::get('/cards/{id}', [CardController::class, 'page'])
    ->name('cards.show');

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $userCards = request()->user()
            ->userCards()
            ->with('card')
            ->get();

        $overall = [
            'unique_cards' => $userCards
                ->pluck('card_id')
                ->unique()
                ->count(),

            'total_cards' => $userCards
                ->sum('quantity'),

            'sets_collected' => $userCards
                ->pluck('card.set_id')
                ->unique()
                ->count(),

            'estimated_value' => round(
                $userCards->sum(function ($userCard) {
                    return $userCard->quantity
                        * ($userCard->market_price ?? 0);
                }),
                2
            ),
        ];

        return Inertia::render('dashboard', [
            'overall' => $overall,
        ]);
    })->name('dashboard');

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
