<?php

use App\Services\TcgDexCardMapper;

beforeEach(function () {
    $this->mapper = new TcgDexCardMapper;
});

/*
|--------------------------------------------------------------------------
| mapBrief
|--------------------------------------------------------------------------
*/

test('a brief card is mapped to the columns stored locally', function () {
    $card = $this->mapper->mapBrief([
        'id' => 'sv8pt5-2',
        'name' => 'Charizard ex',
        'localId' => '2',
        'image' => 'https://images.pokemontcg.io/sv8pt5/2',
    ]);

    expect($card)->toBe([
        'external_id' => 'sv8pt5-2',
        'name' => 'Charizard ex',
        'number' => '2',
        'rarity' => null,
        'image_small' => 'https://images.pokemontcg.io/sv8pt5/2/low.webp',
        'image_large' => 'https://images.pokemontcg.io/sv8pt5/2/high.webp',
        'set' => null,
    ]);
});

test('a brief card without an image has null image urls', function () {
    $card = $this->mapper->mapBrief([
        'id' => 'sv8pt5-2',
        'name' => 'Charizard ex',
    ]);

    expect($card['image_small'])->toBeNull()
        ->and($card['image_large'])->toBeNull();
});

test('a brief card without a local id falls back to an empty number', function () {
    $card = $this->mapper->mapBrief([
        'id' => 'sv8pt5-2',
        'name' => 'Charizard ex',
    ]);

    expect($card['number'])->toBe('');
});

test('a brief card never carries rarity or set data', function () {
    $card = $this->mapper->mapBrief([
        'id' => 'sv8pt5-2',
        'name' => 'Charizard ex',
        'rarity' => 'Double Rare',
        'set' => ['id' => 'sv8pt5'],
    ]);

    expect($card['rarity'])->toBeNull()
        ->and($card['set'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| map
|--------------------------------------------------------------------------
*/

test('a full card is mapped with its set and pricing', function () {
    $card = $this->mapper->map(tcgdexCard());

    expect($card['external_id'])->toBe('sv8pt5-2')
        ->and($card['name'])->toBe('Charizard ex')
        ->and($card['number'])->toBe('2')
        ->and($card['rarity'])->toBe('Double Rare')
        ->and($card['image_small'])->toBe('https://images.pokemontcg.io/sv8pt5/2/low.webp')
        ->and($card['image_large'])->toBe('https://images.pokemontcg.io/sv8pt5/2/high.webp')
        ->and($card['set'])->toBe([
            'external_id' => 'sv8pt5',
            'name' => 'Obsidian Flames',
            'series' => 'Scarlet & Violet',
            'printed_total' => 197,
            'release_date' => '2023-08-11',
            'logo_url' => 'https://images.pokemontcg.io/sv8pt5/logo.webp',
            'symbol_url' => 'https://images.pokemontcg.io/sv8pt5/symbol.webp',
        ]);
});

test('set images are suffixed with the webp extension', function () {
    $card = $this->mapper->map(tcgdexCard([
        'set' => [
            'id' => 'sv8pt5',
            'name' => 'Obsidian Flames',
            'logo' => 'https://images.pokemontcg.io/sv8pt5/logo',
            'symbol' => 'https://images.pokemontcg.io/sv8pt5/symbol',
        ],
    ]));

    expect($card['set']['logo_url'])->toBe('https://images.pokemontcg.io/sv8pt5/logo.webp')
        ->and($card['set']['symbol_url'])->toBe('https://images.pokemontcg.io/sv8pt5/symbol.webp');
});

test('optional set attributes are null when the upstream payload omits them', function () {
    $card = $this->mapper->map(tcgdexCard([
        'set' => ['id' => 'sv8pt5', 'name' => 'Obsidian Flames'],
    ]));

    expect($card['set'])->toBe([
        'external_id' => 'sv8pt5',
        'name' => 'Obsidian Flames',
        'series' => null,
        'printed_total' => null,
        'release_date' => null,
        'logo_url' => null,
        'symbol_url' => null,
    ]);
});

test('a card without a set still maps to a usable array', function () {
    $card = $this->mapper->map(tcgdexCard(['set' => null]));

    expect($card['set']['external_id'])->toBe('')
        ->and($card['set']['name'])->toBe('')
        ->and($card['set']['series'])->toBeNull();
});

test('a card without a rarity maps rarity to null', function () {
    $card = $this->mapper->map([
        'id' => 'sv8pt5-2',
        'name' => 'Charizard ex',
    ]);

    expect($card['rarity'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Pricing
|--------------------------------------------------------------------------
*/

test('tcgplayer and cardmarket pricing are both mapped', function () {
    $prices = $this->mapper->map(tcgdexCard())['prices'];

    expect($prices['tcgplayer']['unit'])->toBe('USD')
        ->and($prices['tcgplayer']['updated'])->toBe('2024-09-01T00:00:00+00:00')
        ->and($prices['tcgplayer']['normal'])->toBe([
            'low' => 1.5,
            'mid' => 2.5,
            'high' => 10.0,
            'market' => 3.25,
            'direct_low' => null,
        ])
        ->and($prices['tcgplayer']['holofoil'])->toBeNull()
        ->and($prices['cardmarket']['unit'])->toBe('EUR')
        ->and($prices['cardmarket']['avg'])->toBe(2.9)
        ->and($prices['cardmarket']['low'])->toBe(1.2)
        ->and($prices['cardmarket']['trend'])->toBe(3.1)
        ->and($prices['cardmarket']['avg1'])->toBeNull()
        ->and($prices['cardmarket']['avg7'])->toBeNull()
        ->and($prices['cardmarket']['avg30'])->toBeNull();
});

test('every tcgplayer variant is mapped from its upstream key', function () {
    $prices = $this->mapper->map(tcgdexCard([
        'pricing' => [
            'tcgplayer' => [
                'normal' => ['marketPrice' => 1.0],
                'holofoil' => ['marketPrice' => 2.0],
                'reverse-holofoil' => ['marketPrice' => 3.0],
                '1st-edition' => ['marketPrice' => 4.0],
            ],
        ],
    ]))['prices'];

    expect($prices['tcgplayer']['normal']['market'])->toBe(1.0)
        ->and($prices['tcgplayer']['holofoil']['market'])->toBe(2.0)
        ->and($prices['tcgplayer']['reverse_holofoil']['market'])->toBe(3.0)
        ->and($prices['tcgplayer']['first_edition']['market'])->toBe(4.0);
});

test('a card without pricing maps prices to null', function () {
    $card = $this->mapper->map(tcgdexCard(['pricing' => null]));

    expect($card['prices'])->toBeNull();
});

test('missing pricing fall back to the upstream default currencies', function () {
    $prices = $this->mapper->map(tcgdexCard([
        'pricing' => ['tcgplayer' => [], 'cardmarket' => []],
    ]))['prices'];

    expect($prices['tcgplayer']['unit'])->toBe('USD')
        ->and($prices['tcgplayer']['normal'])->toBeNull()
        ->and($prices['cardmarket']['unit'])->toBe('EUR')
        ->and($prices['cardmarket']['avg'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| getMarketPrice
|--------------------------------------------------------------------------
*/

test('the market price is read from the normal tcgplayer variant', function () {
    $price = $this->mapper->getMarketPrice(
        $this->mapper->map(tcgdexCard())
    );

    expect($price)->toBe(3.25);
});

test('the market price falls back through the remaining tcgplayer variants', function () {
    $card = [
        'prices' => [
            'tcgplayer' => [
                'normal' => null,
                'holofoil' => null,
                'reverse_holofoil' => null,
                'first_edition' => ['market' => 42.0],
            ],
        ],
    ];

    expect($this->mapper->getMarketPrice($card))->toBe(42.0);
});

test('the market price is null when no variant is priced', function () {
    $card = [
        'prices' => [
            'tcgplayer' => [
                'normal' => null,
                'holofoil' => ['low' => 1.0],
            ],
        ],
    ];

    expect($this->mapper->getMarketPrice($card))->toBeNull();
});

test('the market price is null for a card without pricing', function () {
    expect($this->mapper->getMarketPrice($this->mapper->map(tcgdexCard(['pricing' => null]))))
        ->toBeNull()
        ->and($this->mapper->getMarketPrice([]))->toBeNull();
});

test('a numeric string market price is cast to a float', function () {
    $card = [
        'prices' => [
            'tcgplayer' => [
                'normal' => ['market' => '7.50'],
            ],
        ],
    ];

    expect($this->mapper->getMarketPrice($card))->toBe(7.5);
});

test('a non-numeric market price is discarded rather than returned', function () {
    $card = [
        'prices' => [
            'tcgplayer' => [
                'normal' => ['market' => 'N/A'],
            ],
        ],
    ];

    expect($this->mapper->getMarketPrice($card))->toBeNull();
});

test('a non-numeric market price does not hide a priced fallback variant', function () {
    $card = [
        'prices' => [
            'tcgplayer' => [
                'normal' => ['market' => 'N/A'],
                'holofoil' => ['market' => 18.5],
            ],
        ],
    ];

    expect($this->mapper->getMarketPrice($card))->toBe(18.5);
});

test('variants are preferred in order over the first numeric value found', function () {
    $card = [
        'prices' => [
            'tcgplayer' => [
                'normal' => ['market' => 1.0],
                'holofoil' => ['market' => 2.0],
                'first_edition' => ['market' => 4.0],
            ],
        ],
    ];

    expect($this->mapper->getMarketPrice($card))->toBe(1.0);
});

/*
|--------------------------------------------------------------------------
| mapBriefMany
|--------------------------------------------------------------------------
*/

test('multiple brief cards keep their upstream order', function () {
    $cards = $this->mapper->mapBriefMany([
        ['id' => 'sv8pt5-2', 'name' => 'Charizard ex', 'localId' => '2'],
        ['id' => 'sv8pt5-3', 'name' => 'Pidgeot ex', 'localId' => '3'],
    ]);

    expect($cards)->toHaveCount(2)
        ->and(array_keys($cards))->toBe([0, 1])
        ->and($cards[0]['external_id'])->toBe('sv8pt5-2')
        ->and($cards[1]['external_id'])->toBe('sv8pt5-3');
});

test('mapping an empty list returns an empty list', function () {
    expect($this->mapper->mapBriefMany([]))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Malformed upstream payloads
|--------------------------------------------------------------------------
|
| The service narrows a decoded payload before handing it over, but a card
| object can still be missing keys or carry the wrong type. These cases must
| degrade rather than emit warnings or raise a TypeError, since a mapped
| card is cached for a day.
|
*/

test('a card missing an id or name maps to empty strings without warnings', function () {
    $warnings = [];
    set_error_handler(function (int $number, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $brief = $this->mapper->mapBrief(['image' => 'https://images.test/1']);
        $full = $this->mapper->map(['image' => 'https://images.test/1']);
    } finally {
        restore_error_handler();
    }

    expect($warnings)->toBe([])
        ->and($brief['external_id'])->toBe('')
        ->and($brief['name'])->toBe('')
        ->and($full['external_id'])->toBe('')
        ->and($full['name'])->toBe('');
});

test('a non-string image reference does not produce a broken url', function () {
    expect($this->mapper->mapBrief(['image' => ['nested' => true]])['image_small'])
        ->toBeNull()
        ->and($this->mapper->mapBrief(['image' => 42])['image_small'])
        ->toBe('42/low.webp');
});

test('a non-string set image does not raise a type error', function () {
    $card = $this->mapper->map([
        'id' => 'sv8pt5-2',
        'name' => 'Charizard ex',
        'set' => ['name' => 'Obsidian Flames', 'logo' => ['nested' => true]],
    ]);

    expect($card['set']['logo_url'])->toBeNull()
        ->and($card['set']['name'])->toBe('Obsidian Flames');
});

test('a set that is not an object maps to an empty set', function () {
    $card = $this->mapper->map(['id' => 'a', 'name' => 'b', 'set' => 'nope']);

    expect($card['set'])->toBe([
        'external_id' => '',
        'name' => '',
        'series' => null,
        'printed_total' => null,
        'release_date' => null,
        'logo_url' => null,
        'symbol_url' => null,
    ]);
});

test('a non-numeric printed total becomes null', function () {
    $card = $this->mapper->map([
        'id' => 'a',
        'name' => 'b',
        'set' => ['cardCount' => ['official' => 'many']],
    ]);

    expect($card['set']['printed_total'])->toBeNull();
});

test('scalar pricing sections map to empty price tables', function () {
    $card = $this->mapper->map([
        'id' => 'a',
        'name' => 'b',
        'pricing' => ['tcgplayer' => 'USD', 'cardmarket' => 3],
    ]);

    expect($card['prices']['tcgplayer']['normal'])->toBeNull()
        ->and($card['prices']['cardmarket']['avg'])->toBeNull()
        ->and($card['prices']['tcgplayer']['unit'])->toBe('USD');
});

test('a scalar tcgplayer variant maps to null rather than raising a type error', function () {
    $card = $this->mapper->map([
        'id' => 'a',
        'name' => 'b',
        'pricing' => ['tcgplayer' => ['normal' => 5]],
    ]);

    expect($card['prices']['tcgplayer']['normal'])->toBeNull()
        ->and($this->mapper->getMarketPrice($card))->toBeNull();
});
