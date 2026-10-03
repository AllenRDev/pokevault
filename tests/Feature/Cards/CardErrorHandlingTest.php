<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();

    $this->cardUrl = config('services.tcgdex.base_url').'/cards/missing-card';
});

test('a card that upstream does not know is a 404', function () {
    Http::fake([
        $this->cardUrl => Http::response('Not found', 404),
    ]);

    $this->getJson('/api/cards/missing-card')
        ->assertNotFound();
});

test('a card that upstream does not know is a 404 on the detail page', function () {
    Http::fake([
        $this->cardUrl => Http::response('Not found', 404),
    ]);

    $this->get('/cards/missing-card')
        ->assertNotFound();
});

test('a missing card is not retried', function () {
    Http::fake([
        $this->cardUrl => Http::response('Not found', 404),
    ]);

    $this->getJson('/api/cards/missing-card')->assertNotFound();

    Http::assertSentCount(1);
});

test('an upstream outage still fails the card detail page', function () {
    Http::fake([
        config('services.tcgdex.base_url').'/cards/*' => Http::response('boom', 500),
    ]);

    $this->get('/cards/missing-card')
        ->assertServerError();
});

test('a failed upstream request is not cached as a card', function () {
    Http::fake([
        $this->cardUrl => Http::response('Not found', 404),
    ]);

    $this->getJson('/api/cards/missing-card')->assertNotFound();

    expect(cache()->has('tcgdex.card.missing-card'))->toBeFalse();
});
