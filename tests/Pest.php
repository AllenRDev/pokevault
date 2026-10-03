<?php

use App\Models\Card;
use App\Models\Set;
use App\Models\User;
use App\Models\UserCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A complete TCGdex card payload, shaped like the upstream API response.
 *
 * Keys are merged shallowly, so pass the whole section to replace it:
 * `tcgdexCard(['pricing' => null])`.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tcgdexCard(array $overrides = []): array
{
    return array_replace([
        'id' => 'sv8pt5-2',
        'name' => 'Charizard ex',
        'localId' => '2',
        'image' => 'https://images.pokemontcg.io/sv8pt5/2',
        'rarity' => 'Double Rare',
        'pricing' => [
            'tcgplayer' => [
                'updated' => '2024-09-01T00:00:00+00:00',
                'unit' => 'USD',
                'normal' => [
                    'lowPrice' => 1.5,
                    'midPrice' => 2.5,
                    'highPrice' => 10.0,
                    'marketPrice' => 3.25,
                    'directLowPrice' => null,
                ],
            ],
            'cardmarket' => [
                'updated' => '2024-09-01T00:00:00+00:00',
                'unit' => 'EUR',
                'avg' => 2.9,
                'low' => 1.2,
                'trend' => 3.1,
            ],
        ],
        'set' => [
            'id' => 'sv8pt5',
            'name' => 'Obsidian Flames',
            'serie' => ['name' => 'Scarlet & Violet'],
            'cardCount' => ['official' => 197],
            'releaseDate' => '2023-08-11',
            'logo' => 'https://images.pokemontcg.io/sv8pt5/logo',
            'symbol' => 'https://images.pokemontcg.io/sv8pt5/symbol',
        ],
    ], $overrides);
}

/**
 * Fake the TCGdex API with a single card, served from both the list and
 * detail endpoints.
 *
 * The detail stub is registered first because the list pattern also matches
 * detail URLs, and the first matching stub wins.
 */
function fakeTcgdexApi(?array $card = null): void
{
    $card ??= tcgdexCard();

    $baseUrl = config('services.tcgdex.base_url');

    Http::fake([
        $baseUrl.'/cards/*' => Http::response($card),
        $baseUrl.'/cards*' => Http::response([$card]),
    ]);
}

/**
 * The set and card helpers default to a unique external id so a test can
 * create several of them without tripping the unique index. Pass an
 * explicit `external_id` when the test asserts on it.
 *
 * @param  array<string, mixed>  $attributes
 */
function createSet(array $attributes = []): Set
{
    return Set::create(array_merge([
        'external_id' => 'set-'.Str::random(8),
        'name' => 'Obsidian Flames',
        'series' => 'Scarlet & Violet',
        'printed_total' => 197,
        'release_date' => '2023-08-11',
    ], $attributes));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createCard(?Set $set = null, array $attributes = []): Card
{
    return Card::create(array_merge([
        'external_id' => 'card-'.Str::random(8),
        'set_id' => ($set ?? createSet())->id,
        'name' => 'Charizard ex',
        'number' => '2',
        'rarity' => 'Double Rare',
    ], $attributes));
}

/**
 * The canonical Obsidian Flames card, for tests that assert on the stored
 * external ids. Its ids are fixed, so use it at most once per test.
 */
function charizardCard(): Card
{
    return createCard(createSet([
        'external_id' => 'sv8pt5',
        'name' => 'Obsidian Flames',
        'series' => 'Scarlet & Violet',
        'printed_total' => 197,
        'release_date' => '2023-08-11',
    ]), [
        'external_id' => 'sv8pt5-2',
        'name' => 'Charizard ex',
        'number' => '2',
        'rarity' => 'Double Rare',
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createUserCard(?User $user = null, ?Card $card = null, array $attributes = []): UserCard
{
    return UserCard::create(array_merge([
        'user_id' => ($user ?? User::factory()->create())->id,
        'card_id' => ($card ?? createCard())->id,
        'quantity' => 1,
        'condition' => 'Near Mint',
        'language' => 'English',
    ], $attributes));
}
