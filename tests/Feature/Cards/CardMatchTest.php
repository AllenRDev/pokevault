<?php

use App\Models\Card;
use App\Models\User;
use App\Services\TcgDexService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A search result, which upstream returns as a bare id/name/number list.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tcgdexSearchResult(string $id, string $name, string $localId): array
{
    return [
        'id' => $id,
        'localId' => $localId,
        'name' => $name,
    ];
}

/**
 * A card detail, which upstream returns with the set and its printed size.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tcgdexCardDetail(
    string $id,
    string $name,
    string $localId,
    string $setId,
    string $setName,
    ?int $printedTotal = null
): array {
    return tcgdexCard([
        'id' => $id,
        'name' => $name,
        'localId' => $localId,
        'set' => [
            'id' => $setId,
            'name' => $setName,
            'serie' => ['name' => 'Scarlet & Violet'],
            'cardCount' => ['official' => $printedTotal],
            'releaseDate' => '2023-08-11',
        ],
    ]);
}

beforeEach(function () {
    Cache::flush();
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('a match needs a name', function () {
    $this->getJson('/api/cards/match')
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('a match rejects nonsense numbers', function () {
    $this->getJson('/api/cards/match?name=Charizard&number=abc')
        ->assertStatus(422)
        ->assertJsonValidationErrors('number');
});

/*
|--------------------------------------------------------------------------
| Matching
|--------------------------------------------------------------------------
*/

test('a name and number alone narrow the match but leave it ambiguous', function () {
    Http::fake([
        '*/cards*' => Http::response([
            tcgdexSearchResult('sv8pt5-5', 'Charizard ex', '5'),
            tcgdexSearchResult('pl4-1', 'Charizard', '1'),
        ]),
    ]);

    $this->getJson('/api/cards/match?name=Charizard+ex&number=5')
        ->assertOk()
        ->assertJsonPath('confidence', 'ambiguous')
        ->assertJsonCount(1, 'candidates')
        ->assertJsonPath('candidates.0.external_id', 'sv8pt5-5')
        ->assertJsonPath('query.number', 5)
        ->assertJsonPath('query.total', null);
});

test('the printed set total resolves which printing was scanned', function () {
    Http::fake([
        '*/cards/sv8pt5-5' => Http::response(
            tcgdexCardDetail('sv8pt5-5', 'Charizard ex', '5', 'sv8pt5', 'Obsidian Flames', 197)
        ),
        '*/cards/pl4-5' => Http::response(
            tcgdexCardDetail('pl4-5', 'Charizard ex', '5', 'pl4', 'Paldea Evolved', 99)
        ),
        // Two printings share the name and the number.
        '*/cards*' => Http::response([
            tcgdexSearchResult('sv8pt5-5', 'Charizard ex', '5'),
            tcgdexSearchResult('pl4-5', 'Charizard ex', '5'),
        ]),
    ]);

    // 197 cards in the set is the only detail that fits what was printed.
    $this->getJson('/api/cards/match?name=Charizard+ex&number=5&total=197')
        ->assertOk()
        ->assertJsonPath('confidence', 'exact')
        ->assertJsonCount(1, 'candidates')
        ->assertJsonPath('candidates.0.external_id', 'sv8pt5-5')
        ->assertJsonPath('candidates.0.set.printed_total', 197);
});

test('a total that matches nothing falls back to the number matches', function () {
    Http::fake([
        '*/cards/sv8pt5-5' => Http::response(
            tcgdexCardDetail('sv8pt5-5', 'Charizard ex', '5', 'sv8pt5', 'Obsidian Flames', 197)
        ),
        '*/cards*' => Http::response([
            tcgdexSearchResult('sv8pt5-5', 'Charizard ex', '5'),
        ]),
    ]);

    $this->getJson('/api/cards/match?name=Charizard+ex&number=5&total=250')
        ->assertOk()
        ->assertJsonPath('confidence', 'ambiguous')
        ->assertJsonCount(1, 'candidates')
        ->assertJsonPath('candidates.0.external_id', 'sv8pt5-5');
});

test('a name with no match reports none', function () {
    Http::fake([
        '*/cards*' => Http::response([]),
    ]);

    $this->getJson('/api/cards/match?name=Missingno')
        ->assertOk()
        ->assertJsonPath('confidence', 'none')
        ->assertJsonCount(0, 'candidates');
});

test('a number that matches nothing reports none', function () {
    Http::fake([
        '*/cards*' => Http::response([
            tcgdexSearchResult('pl4-1', 'Charizard', '1'),
        ]),
    ]);

    $this->getJson('/api/cards/match?name=Charizard&number=77')
        ->assertOk()
        ->assertJsonPath('confidence', 'none')
        ->assertJsonCount(0, 'candidates');
});

test('a variant suffix on the card number still matches', function () {
    Http::fake([
        '*/cards*' => Http::response([
            tcgdexSearchResult('pl4-45a', 'Charizard', '45a'),
        ]),
    ]);

    $this->getJson('/api/cards/match?name=Charizard&number=45')
        ->assertOk()
        ->assertJsonCount(1, 'candidates')
        ->assertJsonPath('candidates.0.external_id', 'pl4-45a');
});

test('a candidate whose detail 404s does not break the match', function () {
    Http::fake([
        '*/cards/gone-5' => Http::response(null, 404),
        '*/cards/sv8pt5-5' => Http::response(
            tcgdexCardDetail('sv8pt5-5', 'Charizard ex', '5', 'sv8pt5', 'Obsidian Flames', 197)
        ),
        '*/cards*' => Http::response([
            tcgdexSearchResult('gone-5', 'Charizard ex', '5'),
            tcgdexSearchResult('sv8pt5-5', 'Charizard ex', '5'),
        ]),
    ]);

    $this->getJson('/api/cards/match?name=Charizard+ex&number=5&total=197')
        ->assertOk()
        ->assertJsonPath('confidence', 'exact')
        ->assertJsonCount(1, 'candidates')
        ->assertJsonPath('candidates.0.external_id', 'sv8pt5-5');
});

test('several printings sharing the total stay ambiguous', function () {
    Http::fake([
        '*/cards/a-5' => Http::response(
            tcgdexCardDetail('a-5', 'Charizard ex', '5', 'a', 'First', 99)
        ),
        '*/cards/b-5' => Http::response(
            tcgdexCardDetail('b-5', 'Charizard ex', '5', 'b', 'Second', 99)
        ),
        '*/cards*' => Http::response([
            tcgdexSearchResult('a-5', 'Charizard ex', '5'),
            tcgdexSearchResult('b-5', 'Charizard ex', '5'),
        ]),
    ]);

    $this->getJson('/api/cards/match?name=Charizard+ex&number=5&total=99')
        ->assertOk()
        ->assertJsonPath('confidence', 'ambiguous')
        ->assertJsonCount(2, 'candidates');
});

test('a match never resolves more cards than the candidate limit', function () {
    $results = [];

    foreach (range(1, TcgDexService::MATCH_CANDIDATE_LIMIT + 8) as $index) {
        $results[] = tcgdexSearchResult("bulk-{$index}", 'Charizard ex', '5');
    }

    Http::fake([
        '*/cards*' => Http::response($results),
    ]);

    $this->getJson('/api/cards/match?name=Charizard+ex&number=5')
        ->assertOk()
        ->assertJsonCount(TcgDexService::MATCH_CANDIDATE_LIMIT, 'candidates');
});

test('a matched card can be added to the collection straight after a scan', function () {
    Http::fake([
        '*/cards/sv8pt5-2' => Http::response(
            tcgdexCardDetail('sv8pt5-2', 'Charizard ex', '2', 'sv8pt5', 'Obsidian Flames', 197)
        ),
        '*/cards*' => Http::response([
            tcgdexSearchResult('sv8pt5-2', 'Charizard ex', '2'),
        ]),
    ]);

    $user = User::factory()->create();

    $match = $this->actingAs($user)->getJson(
        '/api/cards/match?name=Charizard+ex&number=2&total=197'
    );

    $externalId = $match->json('candidates.0.external_id');

    $this->actingAs($user)
        ->postJson(route('collection.cards.store'), [
            'external_id' => $externalId,
            'quantity' => 1,
            'condition' => 'Near Mint',
            'language' => 'English',
        ])
        ->assertCreated();

    expect(Card::where('external_id', $externalId)->exists())->toBeTrue();
});
