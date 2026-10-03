<?php

use App\Models\User;

beforeEach(function () {
    /*
     * Inertia pages are asserted on, not rendered, so the tests must not
     * depend on a fresh frontend build being present.
     */
    $this->withoutVite();

    fakeTcgdexApi();
});

/*
|--------------------------------------------------------------------------
| GET /api/cards/{id}
|--------------------------------------------------------------------------
*/

test('guests can view a card', function () {
    $response = $this->getJson('/api/cards/sv8pt5-2');

    $response
        ->assertOk()
        ->assertJsonPath('external_id', 'sv8pt5-2')
        ->assertJsonPath('name', 'Charizard ex')
        ->assertJsonPath('set.external_id', 'sv8pt5')
        ->assertJsonPath('prices.tcgplayer.normal.market', 3.25);
});

test('the card endpoint does not expose upstream payload keys', function () {
    $this->getJson('/api/cards/sv8pt5-2')
        ->assertOk()
        ->assertJsonMissingPath('localId')
        ->assertJsonMissingPath('id')
        ->assertJsonMissingPath('cardCount');
});

/*
|--------------------------------------------------------------------------
| GET /cards/{id}
|--------------------------------------------------------------------------
*/

test('guests can view the card detail page', function () {
    $this->get(route('cards.show', 'sv8pt5-2'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('cards/show')
            ->where('card.external_id', 'sv8pt5-2')
            ->where('card.name', 'Charizard ex')
            ->where('isAuthenticated', false),
        );
});

test('authenticated users are flagged on the card detail page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cards.show', 'sv8pt5-2'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('cards/show')
            ->where('isAuthenticated', true),
        );
});

test('the card detail page exposes the mapped prices', function () {
    $this->get(route('cards.show', 'sv8pt5-2'))
        ->assertInertia(fn ($page) => $page
            ->component('cards/show')
            ->where('card.prices.tcgplayer.unit', 'USD')
            ->where('card.prices.cardmarket.unit', 'EUR')
            ->has('card.set'),
        );
});

/*
|--------------------------------------------------------------------------
| Static pages
|--------------------------------------------------------------------------
*/

test('guests can view the card search page', function () {
    $this->get(route('cards.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('cards/index'));
});

test('guests cannot view the card scanner', function () {
    $this->get(route('cards.scan'))
        ->assertRedirect(route('login'));
});

test('users can view the card scanner', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cards.scan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('cards/scan'));
});

test('the literal scan route wins over the card detail route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/cards/scan')
        ->assertInertia(fn ($page) => $page->component('cards/scan'));
});
