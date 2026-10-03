<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
});

test('guests can view the home page', function () {
    fakeTcgdexApi();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('featuredCards', 1)
            ->where('featuredCards.0.external_id', 'sv8pt5-2')
            ->where('featuredCards.0.name', 'Charizard ex'),
        );
});

test('the home page shows at most four featured cards', function () {
    Http::fake([
        config('services.tcgdex.base_url').'/cards*' => Http::response(array_map(
            fn (int $index): array => tcgdexCard([
                'id' => 'featured-'.$index,
                'name' => 'Featured '.$index,
            ]),
            range(1, 7)
        )),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('featuredCards', 4)
            ->where('featuredCards.0.external_id', 'featured-1')
            ->where('featuredCards.3.external_id', 'featured-4'),
        );
});

test('featured cards are brief mappings without pricing', function () {
    fakeTcgdexApi();

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->has('featuredCards.0.set', null)
            ->where('featuredCards.0.rarity', null)
            ->where('featuredCards.0.image_small', 'https://images.pokemontcg.io/sv8pt5/2/low.webp')
            ->missing('featuredCards.0.prices'),
        );
});

test('the home page renders without featured cards when the api is down', function () {
    Http::fake([
        config('services.tcgdex.base_url').'/cards*' => Http::response('boom', 500),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('featuredCards', 0),
        );
});

test('the home page renders when the api cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('featuredCards', 0));
});
