<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'api.tcgdex.net/*' => Http::response([
            [
                'id' => 'sv8pt5-2',
                'name' => 'Charizard',
                'localId' => '2',
                'image' => 'https://images.pokemontcg.io/sv8pt5/2',
            ],
        ]),
    ]);
});

test('guests can search for cards', function () {
    $response = $this->getJson('/api/cards/search?q=charizard');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('count', 1)
        ->assertJsonPath('data.0.external_id', 'sv8pt5-2');
});

test('the search query is validated', function () {
    $this->getJson('/api/cards/search?q=a')
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');

    $this->getJson('/api/cards/search')
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');
});

test('search requests are rate limited', function () {
    foreach (range(1, 60) as $attempt) {
        $this->getJson('/api/cards/search?q=charizard')->assertOk();
    }

    $this->getJson('/api/cards/search?q=charizard')
        ->assertStatus(429);
});
