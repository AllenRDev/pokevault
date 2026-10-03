<?php

use App\Models\Card;
use App\Models\Set;
use App\Models\User;
use App\Models\UserCard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A valid payload for POST /collection/cards.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storePayload(array $overrides = []): array
{
    return array_merge([
        'external_id' => 'sv8pt5-2',
        'quantity' => 1,
        'condition' => 'Near Mint',
        'language' => 'English',
    ], $overrides);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/*
|--------------------------------------------------------------------------
| Authorization and validation
|--------------------------------------------------------------------------
*/

test('guests cannot add a card to their collection', function () {
    auth()->logout();

    $this->post(route('collection.cards.store'), storePayload())
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('user_cards', 0);
});

test('a card is added with the minimum required fields', function () {
    fakeTcgdexApi();

    $this->post(route('collection.cards.store'), storePayload())
        ->assertCreated()
        ->assertJsonPath('message', 'Card added to your collection.')
        ->assertJsonPath('user_card.quantity', 1)
        ->assertJsonPath('user_card.condition', 'Near Mint')
        ->assertJsonPath('user_card.language', 'English');
});

test('the external id is required', function () {
    $this->postJson(route('collection.cards.store'), storePayload(['external_id' => null]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('external_id');

    $this->assertDatabaseCount('user_cards', 0);
});

test('the quantity is required and must be between one and 999', function () {
    $this->postJson(route('collection.cards.store'), storePayload(['quantity' => null]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('quantity');

    $this->postJson(route('collection.cards.store'), storePayload(['quantity' => 0]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('quantity');

    $this->postJson(route('collection.cards.store'), storePayload(['quantity' => 1000]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('quantity');

    $this->postJson(route('collection.cards.store'), storePayload(['quantity' => 'two']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('quantity');
});

test('the condition must be one of the supported grades', function () {
    fakeTcgdexApi();

    foreach ([
        'Near Mint',
        'Lightly Played',
        'Moderately Played',
        'Heavily Played',
        'Damaged',
    ] as $condition) {
        $this->postJson(route('collection.cards.store'), storePayload([
            'condition' => $condition,
        ]))->assertCreated();
    }

    $this->postJson(route('collection.cards.store'), storePayload([
        'condition' => 'Mint',
    ]))->assertStatus(422)->assertJsonValidationErrors('condition');
});

test('the language must be one of the supported languages', function () {
    fakeTcgdexApi();

    foreach ([
        'English',
        'Japanese',
        'Korean',
        'Chinese',
        'French',
        'German',
        'Italian',
        'Spanish',
        'Portuguese',
    ] as $language) {
        $this->postJson(route('collection.cards.store'), storePayload([
            'language' => $language,
        ]))->assertCreated();
    }

    $this->postJson(route('collection.cards.store'), storePayload([
        'language' => 'Klingon',
    ]))->assertStatus(422)->assertJsonValidationErrors('language');
});

test('the purchase price cannot be negative or non-numeric', function () {
    $this->postJson(route('collection.cards.store'), storePayload(['purchase_price' => -1]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('purchase_price');

    $this->postJson(route('collection.cards.store'), storePayload(['purchase_price' => 'free']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('purchase_price');
});

test('the purchase date must be a date', function () {
    $this->postJson(route('collection.cards.store'), storePayload(['purchase_date' => 'yesterday']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('purchase_date');
});

test('the notes are limited in length', function () {
    $this->postJson(route('collection.cards.store'), storePayload([
        'notes' => str_repeat('a', 2001),
    ]))->assertStatus(422)->assertJsonValidationErrors('notes');
});

test('optional fields are optional', function () {
    fakeTcgdexApi();

    $this->postJson(route('collection.cards.store'), storePayload())
        ->assertCreated()
        ->assertJsonPath('user_card.purchase_price', null)
        ->assertJsonPath('user_card.purchase_date', null)
        ->assertJsonPath('user_card.notes', null);
});

test('a card upstream does not know is a 404', function () {
    Http::fake([
        config('services.tcgdex.base_url').'/cards/*' => Http::response('Not found', 404),
    ]);

    $this->postJson(route('collection.cards.store'), storePayload([
        'external_id' => 'does-not-exist',
    ]))->assertNotFound();

    $this->assertDatabaseCount('user_cards', 0);
});

/*
|--------------------------------------------------------------------------
| Card and set persistence
|--------------------------------------------------------------------------
*/

test('the card and its set are stored locally', function () {
    fakeTcgdexApi();

    $this->postJson(route('collection.cards.store'), storePayload())
        ->assertCreated();

    $this->assertDatabaseHas('sets', [
        'external_id' => 'sv8pt5',
        'name' => 'Obsidian Flames',
        'series' => 'Scarlet & Violet',
        'printed_total' => 197,
        'release_date' => '2023-08-11',
        'logo_url' => 'https://images.pokemontcg.io/sv8pt5/logo.webp',
        'symbol_url' => 'https://images.pokemontcg.io/sv8pt5/symbol.webp',
    ]);

    $this->assertDatabaseHas('cards', [
        'external_id' => 'sv8pt5-2',
        'name' => 'Charizard ex',
        'number' => '2',
        'rarity' => 'Double Rare',
        'image_small' => 'https://images.pokemontcg.io/sv8pt5/2/low.webp',
        'image_large' => 'https://images.pokemontcg.io/sv8pt5/2/high.webp',
    ]);

    $this->assertDatabaseHas('user_cards', [
        'user_id' => $this->user->id,
        'quantity' => 1,
        'condition' => 'Near Mint',
        'language' => 'English',
    ]);
});

test('the card belongs to the set it was fetched with', function () {
    fakeTcgdexApi();

    $this->postJson(route('collection.cards.store'), storePayload())
        ->assertCreated()
        ->assertJsonPath('user_card.card.set.external_id', 'sv8pt5')
        ->assertJsonPath('user_card.card.set.name', 'Obsidian Flames');
});

test('adding a second card from the same set reuses the set row', function () {
    Http::fake([
        config('services.tcgdex.base_url').'/cards/sv8pt5-3' => Http::response(
            tcgdexCard([
                'id' => 'sv8pt5-3',
                'name' => 'Pidgeot ex',
                'localId' => '3',
            ])
        ),
        config('services.tcgdex.base_url').'/cards/*' => Http::response(tcgdexCard()),
    ]);

    $this->postJson(route('collection.cards.store'), storePayload())->assertCreated();

    $this->postJson(route('collection.cards.store'), storePayload([
        'external_id' => 'sv8pt5-3',
        'condition' => 'Damaged',
    ]))->assertCreated();

    expect(Set::count())->toBe(1)
        ->and(Card::count())->toBe(2)
        ->and(UserCard::count())->toBe(2);
});

test('adding a card another user already owns reuses the card row', function () {
    fakeTcgdexApi();

    $other = User::factory()->create();

    $this->actingAs($other)
        ->postJson(route('collection.cards.store'), storePayload())
        ->assertCreated();

    $this->actingAs($this->user)
        ->postJson(route('collection.cards.store'), storePayload())
        ->assertCreated();

    expect(Set::count())->toBe(1)
        ->and(Card::count())->toBe(1)
        ->and(UserCard::count())->toBe(2);
});

test('a card refetched with new details updates the stored card', function () {
    $card = charizardCard();

    Http::fake([
        config('services.tcgdex.base_url').'/cards/*' => Http::response(
            tcgdexCard(['name' => 'Charizard ex (Reverse)', 'rarity' => 'Secret Rare'])
        ),
    ]);

    $this->postJson(route('collection.cards.store'), storePayload())->assertCreated();

    expect(Card::count())->toBe(1);

    $card->refresh();

    expect($card->name)->toBe('Charizard ex (Reverse)')
        ->and($card->rarity)->toBe('Secret Rare');
});

/*
|--------------------------------------------------------------------------
| Market price
|--------------------------------------------------------------------------
*/

test('the current market price is stored with a timestamp', function () {
    fakeTcgdexApi();

    $this->postJson(route('collection.cards.store'), storePayload())->assertCreated();

    $userCard = UserCard::sole();

    expect((float) $userCard->market_price)->toBe(3.25)
        ->and($userCard->market_price_updated_at)->not->toBeNull();
});

test('a card without a market price stores a null price and no timestamp', function () {
    Http::fake([
        config('services.tcgdex.base_url').'/cards/*' => Http::response(
            tcgdexCard(['pricing' => null])
        ),
    ]);

    $this->postJson(route('collection.cards.store'), storePayload())->assertCreated();

    $userCard = UserCard::sole();

    expect($userCard->market_price)->toBeNull()
        ->and($userCard->market_price_updated_at)->toBeNull();
});

test('a holofoil market price is used when the normal variant is unpriced', function () {
    Http::fake([
        config('services.tcgdex.base_url').'/cards/*' => Http::response(
            tcgdexCard([
                'pricing' => [
                    'tcgplayer' => [
                        'normal' => null,
                        'holofoil' => ['marketPrice' => 18.5],
                    ],
                ],
            ])
        ),
    ]);

    $this->postJson(route('collection.cards.store'), storePayload())->assertCreated();

    expect((float) UserCard::sole()->market_price)->toBe(18.5);
});

/*
|--------------------------------------------------------------------------
| Duplicate cards
|--------------------------------------------------------------------------
*/

test('adding the same card in the same condition increases the quantity', function () {
    fakeTcgdexApi();

    $this->postJson(route('collection.cards.store'), storePayload(['quantity' => 1]))
        ->assertCreated()
        ->assertJsonPath('user_card.quantity', 1);

    $this->postJson(route('collection.cards.store'), storePayload(['quantity' => 2]))
        ->assertCreated()
        ->assertJsonPath('user_card.quantity', 3);

    expect(UserCard::count())->toBe(1)
        ->and(UserCard::sole()->quantity)->toBe(3);
});

test('accumulating a quantity stops at the maximum', function () {
    fakeTcgdexApi();

    $this->postJson(route('collection.cards.store'), storePayload(['quantity' => 900]))
        ->assertCreated();

    $this->postJson(route('collection.cards.store'), storePayload(['quantity' => 900]))
        ->assertCreated()
        ->assertJsonPath('user_card.quantity', UserCard::MAX_QUANTITY);

    expect(UserCard::sole()->quantity)->toBe(UserCard::MAX_QUANTITY);
});

test('the same card in a different condition is tracked separately', function () {
    fakeTcgdexApi();

    $this->postJson(route('collection.cards.store'), storePayload(['condition' => 'Near Mint']))
        ->assertCreated()
        ->assertJsonPath('user_card.quantity', 1);

    $this->postJson(route('collection.cards.store'), storePayload([
        'condition' => 'Lightly Played',
        'quantity' => 1,
    ]))->assertCreated();

    expect(UserCard::count())->toBe(2)
        ->and(UserCard::where('condition', 'Lightly Played')->sole()->quantity)->toBe(1);
});

test('the same card in a different language is tracked separately', function () {
    fakeTcgdexApi();

    $this->postJson(route('collection.cards.store'), storePayload(['language' => 'English']))
        ->assertCreated();

    $this->postJson(route('collection.cards.store'), storePayload(['language' => 'Japanese']))
        ->assertCreated();

    expect(UserCard::count())->toBe(2)
        ->and(UserCard::where('language', 'Japanese')->sole()->quantity)->toBe(1);
});

test('omitted optional fields keep the values from the first add', function () {
    fakeTcgdexApi();

    $this->postJson(route('collection.cards.store'), storePayload([
        'quantity' => 1,
        'purchase_price' => 12.50,
        'purchase_date' => '2024-01-15',
        'notes' => 'First copy',
    ]))->assertCreated();

    $this->postJson(route('collection.cards.store'), storePayload(['quantity' => 1]))
        ->assertCreated();

    $userCard = UserCard::sole();

    expect($userCard->quantity)->toBe(2)
        ->and((float) $userCard->purchase_price)->toBe(12.50)
        ->and($userCard->purchase_date->toDateString())->toBe('2024-01-15')
        ->and($userCard->notes)->toBe('First copy');
});

test('optional fields sent as null clear the stored value', function () {
    fakeTcgdexApi();

    $this->postJson(route('collection.cards.store'), storePayload([
        'purchase_price' => 12.50,
        'notes' => 'First copy',
    ]))->assertCreated();

    $this->postJson(route('collection.cards.store'), storePayload([
        'purchase_price' => null,
        'notes' => null,
    ]))->assertCreated();

    $userCard = UserCard::sole();

    expect($userCard->purchase_price)->toBeNull()
        ->and($userCard->notes)->toBeNull();
});

test('a new market price is refreshed when the card is added again', function () {
    /*
     * The first request resolves at the default price, the second at an
     * updated one, so a single stub has to vary by call.
     */
    $request = 0;

    Http::fake([
        config('services.tcgdex.base_url').'/cards/*' => function () use (&$request) {
            $request++;

            return Http::response($request === 1
                ? tcgdexCard()
                : tcgdexCard([
                    'pricing' => [
                        'tcgplayer' => [
                            'normal' => ['marketPrice' => 9.99],
                        ],
                    ],
                ]));
        },
    ]);

    $this->postJson(route('collection.cards.store'), storePayload())->assertCreated();

    $firstSeen = UserCard::sole()->market_price_updated_at;

    $this->travel(1)->hours();

    Cache::forget('tcgdex.card.sv8pt5-2');

    $this->postJson(route('collection.cards.store'), storePayload())->assertCreated();

    $userCard = UserCard::sole();

    expect((float) $userCard->market_price)->toBe(9.99)
        ->and($userCard->market_price_updated_at->greaterThan($firstSeen))->toBeTrue();
});

test('a stale market price is cleared when upstream stops quoting one', function () {
    $request = 0;

    Http::fake([
        config('services.tcgdex.base_url').'/cards/*' => function () use (&$request) {
            $request++;

            return Http::response($request === 1
                ? tcgdexCard()
                : tcgdexCard(['pricing' => null]));
        },
    ]);

    $this->postJson(route('collection.cards.store'), storePayload())->assertCreated();

    expect((float) UserCard::sole()->market_price)->toBe(3.25);

    Cache::forget('tcgdex.card.sv8pt5-2');

    $this->postJson(route('collection.cards.store'), storePayload())->assertCreated();

    $userCard = UserCard::sole();

    expect($userCard->market_price)->toBeNull()
        ->and($userCard->market_price_updated_at)->toBeNull()
        ->and($userCard->quantity)->toBe(2);
});

test('adding a card again refetches the price instead of reusing the cache', function () {
    fakeTcgdexApi();

    $this->postJson(route('collection.cards.store'), storePayload())->assertCreated();

    $this->travel(1)->hours();

    $this->postJson(route('collection.cards.store'), storePayload())->assertCreated();

    expect((float) UserCard::sole()->market_price)->toBe(3.25);

    Http::assertSentCount(2);
});

test('a non-numeric normal price falls through to a valid holofoil price', function () {
    Http::fake([
        config('services.tcgdex.base_url').'/cards/*' => Http::response(
            tcgdexCard([
                'pricing' => [
                    'tcgplayer' => [
                        'normal' => ['marketPrice' => 'N/A'],
                        'holofoil' => ['marketPrice' => 18.5],
                    ],
                ],
            ])
        ),
    ]);

    $this->postJson(route('collection.cards.store'), storePayload())->assertCreated();

    expect((float) UserCard::sole()->market_price)->toBe(18.5);
});
