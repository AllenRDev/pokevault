<?php

use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * The collection routes that must never be reachable without a session.
 */
dataset('protected routes', [
    'collection page' => [fn () => route('collection.index')],
    'collection card list' => [fn () => route('collection.cards.index')],
]);

test('guests are redirected away from the collection', function (string $route) {
    $this->get($route)->assertRedirect(route('login'));
})->with('protected routes');

test('guests cannot write to the collection', function () {
    $userCard = createUserCard(User::factory()->create());

    $this->post(route('collection.cards.store'), [])->assertRedirect(route('login'));

    $this->put(route('collection.cards.update', $userCard), [])->assertRedirect(route('login'));

    $this->delete(route('collection.cards.destroy', $userCard))->assertRedirect(route('login'));
});

test('users can view the collection page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('collection.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('collection/index'));
});

test('users can view their collection cards', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('collection.cards.index'))
        ->assertOk();
});

test('the collection card list is only reachable for a session', function () {
    $this->get(route('collection.cards.index'))
        ->assertRedirect(route('login'));
});
