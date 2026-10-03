<?php

use App\Exceptions\CardNotFoundException;
use App\Services\TcgDexService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = app(TcgDexService::class);

    $this->listUrl = config('services.tcgdex.base_url').'/cards';
    $this->detailUrl = config('services.tcgdex.base_url').'/cards/sv8pt5-2';
});

/*
|--------------------------------------------------------------------------
| searchCards
|--------------------------------------------------------------------------
*/

test('cards are searched by name and returned as a mapped list', function () {
    fakeTcgdexApi();

    $result = $this->service->searchCards('charizard');

    expect($result)->toBe([
        'data' => [[
            'external_id' => 'sv8pt5-2',
            'name' => 'Charizard ex',
            'number' => '2',
            'rarity' => null,
            'image_small' => 'https://images.pokemontcg.io/sv8pt5/2/low.webp',
            'image_large' => 'https://images.pokemontcg.io/sv8pt5/2/high.webp',
            'set' => null,
        ]],
        'count' => 1,
    ]);

    Http::assertSent(fn ($request) => $request->url() === $this->listUrl.'?name=charizard'
        && $request['name'] === 'charizard');
});

test('the search count reflects every upstream match, not the page size', function () {
    Http::fake([
        $this->listUrl.'*' => Http::response([
            tcgdexCard(['id' => 'a', 'name' => 'Charizard ex']),
            tcgdexCard(['id' => 'b', 'name' => 'Charizard']),
            tcgdexCard(['id' => 'c', 'name' => 'Charizard 2']),
        ]),
    ]);

    $result = $this->service->searchCards('charizard');

    expect($result['count'])->toBe(3)
        ->and($result['data'])->toHaveCount(3);
});

test('a search result is cached across repeated identical queries', function () {
    fakeTcgdexApi();

    $this->service->searchCards('charizard');
    $this->service->searchCards('charizard');

    Http::assertSentCount(1);
});

test('the search cache key ignores case and surrounding whitespace', function () {
    fakeTcgdexApi();

    $this->service->searchCards('Charizard');
    $this->service->searchCards('  CHARIZARD  ');

    Http::assertSentCount(1);
});

test('different search queries are cached separately', function () {
    fakeTcgdexApi();

    $this->service->searchCards('charizard');
    $this->service->searchCards('pikachu');

    Http::assertSentCount(2);
});

/*
|--------------------------------------------------------------------------
| getCard
|--------------------------------------------------------------------------
*/

test('a single card is fetched and fully mapped', function () {
    fakeTcgdexApi();

    $card = $this->service->getCard('sv8pt5-2');

    expect($card['external_id'])->toBe('sv8pt5-2')
        ->and($card['set']['external_id'])->toBe('sv8pt5')
        ->and($card['set']['printed_total'])->toBe(197)
        ->and($card['prices']['tcgplayer']['normal']['market'])->toBe(3.25);

    Http::assertSent(fn ($request) => $request->url() === $this->detailUrl);
});

test('a single card is cached across repeated requests', function () {
    fakeTcgdexApi();

    $this->service->getCard('sv8pt5-2');
    $this->service->getCard('sv8pt5-2');

    Http::assertSentCount(1);
});

test('an upstream failure throws', function () {
    Http::fake([
        $this->detailUrl => Http::response('boom', 500),
    ]);

    expect(fn () => $this->service->getCard('sv8pt5-2'))
        ->toThrow(RequestException::class);
});

test('a card upstream does not have is reported as not found', function () {
    Http::fake([
        $this->detailUrl => Http::response('Not found', 404),
    ]);

    expect(fn () => $this->service->getCard('sv8pt5-2'))
        ->toThrow(CardNotFoundException::class, 'TCGdex has no card with the id [sv8pt5-2].');
});

test('a missing card is not retried', function () {
    Http::fake([
        $this->detailUrl => Http::response('Not found', 404),
    ]);

    expect(fn () => $this->service->getCard('sv8pt5-2'))
        ->toThrow(CardNotFoundException::class);

    Http::assertSentCount(1);
});

test('a missing card is not cached', function () {
    Http::fake([
        $this->detailUrl => Http::response('Not found', 404),
    ]);

    expect(fn () => $this->service->getCard('sv8pt5-2'))
        ->toThrow(CardNotFoundException::class);

    expect(Cache::has('tcgdex.card.sv8pt5-2'))->toBeFalse();
});

test('a fresh card request bypasses and repopulates the cache', function () {
    fakeTcgdexApi();

    $this->service->getCard('sv8pt5-2');
    $this->service->getCard('sv8pt5-2');

    Http::assertSentCount(1);

    $this->service->getCard('sv8pt5-2', fresh: true);

    Http::assertSentCount(2);
});

test('a fresh card request is what the cache is repopulated with', function () {
    $request = 0;

    Http::fake([
        $this->detailUrl => function () use (&$request) {
            $request++;

            return Http::response(tcgdexCard([
                'name' => 'Charizard ex #'.$request,
            ]));
        },
    ]);

    $this->service->getCard('sv8pt5-2', fresh: true);

    expect($this->service->getCard('sv8pt5-2')['name'])->toBe('Charizard ex #1');

    $this->service->getCard('sv8pt5-2', fresh: true);

    expect($this->service->getCard('sv8pt5-2')['name'])->toBe('Charizard ex #2');
});

/*
|--------------------------------------------------------------------------
| getFeaturedCards
|--------------------------------------------------------------------------
*/

test('only four featured cards are returned', function () {
    Http::fake([
        $this->listUrl.'*' => Http::response(array_map(
            fn (int $index): array => tcgdexCard([
                'id' => 'featured-'.$index,
                'name' => 'Featured '.$index,
            ]),
            range(1, 6)
        )),
    ]);

    $cards = $this->service->getFeaturedCards();

    expect($cards)->toHaveCount(4)
        ->and($cards[0]['external_id'])->toBe('featured-1')
        ->and($cards[3]['external_id'])->toBe('featured-4');
});

test('featured cards are returned as brief mappings without a set', function () {
    fakeTcgdexApi();

    $cards = $this->service->getFeaturedCards();

    expect($cards[0]['set'])->toBeNull()
        ->and($cards[0]['rarity'])->toBeNull();
});

test('featured cards are cached', function () {
    fakeTcgdexApi();

    $this->service->getFeaturedCards();
    $this->service->getFeaturedCards();

    Http::assertSentCount(1);
});

/*
|--------------------------------------------------------------------------
| Malformed upstream payloads
|--------------------------------------------------------------------------
*/

test('non-object entries are dropped from a list payload', function () {
    Http::fake([
        $this->listUrl.'*' => Http::response([
            tcgdexCard(),
            'not a card',
            null,
            42,
        ]),
    ]);

    $result = $this->service->searchCards('charizard');

    expect($result['count'])->toBe(1)
        ->and($result['data'])->toHaveCount(1);
});

test('a list payload that is not an array degrades to an empty result', function () {
    Http::fake([
        $this->listUrl.'*' => Http::response('nope', 200),
    ]);

    $result = $this->service->searchCards('charizard');

    expect($result)->toBe(['data' => [], 'count' => 0]);
});

test('a detail payload that is not an array is rejected', function () {
    Http::fake([
        $this->detailUrl => Http::response('nope', 200),
    ]);

    expect(fn () => $this->service->getCard('sv8pt5-2'))
        ->toThrow(UnexpectedValueException::class);
});

/*
|--------------------------------------------------------------------------
| Retries
|--------------------------------------------------------------------------
*/

test('a transient upstream failure is retried', function () {
    Http::fakeSequence()
        ->push('', 500)
        ->push('', 500)
        ->push(tcgdexCard(), 200);

    $card = $this->service->getCard('sv8pt5-2');

    expect($card['external_id'])->toBe('sv8pt5-2');

    Http::assertSentCount(3);
});

test('the request is retried at most three times before failing', function () {
    Http::fake([
        $this->detailUrl => Http::response('boom', 500),
    ]);

    expect(fn () => $this->service->getCard('sv8pt5-2'))
        ->toThrow(RequestException::class);

    Http::assertSentCount(3);
});

/*
|--------------------------------------------------------------------------
| Cache stores
|--------------------------------------------------------------------------
*/

test('cached card data is reused from the cache store', function () {
    Cache::put('tcgdex.card.sv8pt5-2', ['external_id' => 'from-cache'], 3600);

    Http::fake();

    $card = $this->service->getCard('sv8pt5-2');

    expect($card)->toBe(['external_id' => 'from-cache']);

    Http::assertNothingSent();
});

test('featured cards are searched for by the configured card', function () {
    fakeTcgdexApi();

    $this->service->getFeaturedCards();

    Http::assertSent(fn ($request) => $request->data()['name'] === 'Charizard');
});

test('the featured card can be overridden', function () {
    config(['services.tcgdex.featured_card' => 'Mew']);

    fakeTcgdexApi();

    $this->service->getFeaturedCards();

    Http::assertSent(fn ($request) => $request->data()['name'] === 'Mew');
});
