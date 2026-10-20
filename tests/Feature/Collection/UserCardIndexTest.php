<?php

use App\Http\Controllers\UserCardController;
use App\Models\User;
use App\Models\UserCard;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('guests cannot list their collection cards', function () {
    auth()->logout();

    $this->get(route('collection.cards.index'))
        ->assertRedirect(route('login'));
});

test('a new collection starts empty', function () {
    $this->getJson(route('collection.cards.index'))
        ->assertOk()
        ->assertExactJson([
            'data' => [],
            'summary' => [
                'total_cards' => 0,
                'unique_cards' => 0,
                'sets_collected' => 0,
                'estimated_value' => 0,
                'portfolio_value' => 0,
            ],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => UserCardController::DEFAULT_PER_PAGE,
                'from' => null,
                'to' => null,
                'total' => 0,
            ],
        ]);
});

test('the collection lists the cards the user owns', function () {
    $userCard = createUserCard($this->user, charizardCard());

    $this->getJson(route('collection.cards.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $userCard->id)
        ->assertJsonPath('data.0.quantity', 1)
        ->assertJsonPath('data.0.condition', UserCard::DEFAULT_CONDITION)
        ->assertJsonPath('data.0.language', 'English');
});

test('each collection card includes its card and set', function () {
    createUserCard($this->user, charizardCard());

    $this->getJson(route('collection.cards.index'))
        ->assertOk()
        ->assertJsonPath('data.0.card.external_id', 'sv8pt5-2')
        ->assertJsonPath('data.0.card.name', 'Charizard ex')
        ->assertJsonPath('data.0.card.set.external_id', 'sv8pt5')
        ->assertJsonPath('data.0.card.set.name', 'Obsidian Flames');
});

test('the collection never includes another user cards', function () {
    $other = User::factory()->create();

    createUserCard($this->user);
    createUserCard($other, createCard(attributes: [
        'external_id' => 'sv3pt5-1',
        'name' => 'Mewtwo ex',
    ]));

    $this->getJson(route('collection.cards.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $this->user->id)
        ->assertJsonPath('meta.total', 1);
});

test('the newest collection card is listed first', function () {
    $this->travelTo(now()->startOfDay());

    $oldest = createUserCard($this->user);

    $this->travel(1)->hour();

    $middle = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'sv8pt5-3',
        'name' => 'Pidgeot ex',
    ]));

    $this->travel(1)->hour();

    $newest = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'sv8pt5-4',
        'name' => 'Froakie ex',
    ]));

    $this->getJson(route('collection.cards.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $newest->id)
        ->assertJsonPath('data.1.id', $middle->id)
        ->assertJsonPath('data.2.id', $oldest->id);
});

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

test('the collection is paged', function () {
    foreach (range(1, 15) as $index) {
        createUserCard($this->user, createCard(attributes: [
            'external_id' => 'paged-'.$index,
        ]));
    }

    $this->getJson(route('collection.cards.index', ['per_page' => 12]))
        ->assertOk()
        ->assertJsonCount(12, 'data')
        ->assertJsonPath('meta.total', 15)
        ->assertJsonPath('meta.per_page', 12)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.from', 1)
        ->assertJsonPath('meta.to', 12);

    $this->getJson(route('collection.cards.index', ['per_page' => 12, 'page' => 2]))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.from', 13)
        ->assertJsonPath('meta.to', 15);
});

test('a page beyond the last one holds no cards', function () {
    createUserCard($this->user);

    $this->getJson(route('collection.cards.index', ['per_page' => 12, 'page' => 9]))
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.current_page', 9);
});

test('the per page value is bounded', function () {
    $this->getJson(route('collection.cards.index', ['per_page' => 5000]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('per_page');
});

test('the per page value accepts its boundaries', function () {
    createUserCard($this->user);

    $this->getJson(route('collection.cards.index', ['per_page' => 12]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 12);

    $this->getJson(route('collection.cards.index', ['per_page' => 96]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 96);

    $this->getJson(route('collection.cards.index', ['per_page' => 11]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('per_page');

    $this->getJson(route('collection.cards.index', ['per_page' => 97]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('per_page');
});

/*
|--------------------------------------------------------------------------
| Searching and sorting
|--------------------------------------------------------------------------
*/

test('the collection can be searched by card name', function () {
    $charizard = createUserCard($this->user, charizardCard());

    createUserCard($this->user, createCard(attributes: [
        'external_id' => 'sv8pt5-3',
        'name' => 'Pidgeot ex',
    ]));

    $this->getJson(route('collection.cards.index', ['search' => 'charizard']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $charizard->id)
        ->assertJsonPath('meta.total', 1);
});

test('the collection can be searched by set name', function () {
    $obsidian = createUserCard($this->user, charizardCard());

    createUserCard($this->user, createCard(createSet(attributes: [
        'external_id' => 'sv3pt5',
        'name' => 'Obsidian Veil',
    ]), attributes: [
        'external_id' => 'sv3pt5-1',
        'name' => 'Mewtwo ex',
    ]));

    $this->getJson(route('collection.cards.index', ['search' => 'Obsidian Flames']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $obsidian->id);
});

test('the collection can be searched by card number', function () {
    $match = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'sv8pt5-77',
        'number' => '77',
    ]));

    createUserCard($this->user, createCard(attributes: [
        'external_id' => 'sv8pt5-78',
        'number' => '78',
    ]));

    $this->getJson(route('collection.cards.index', ['search' => '77']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

test('a search does not reach another user cards', function () {
    $other = User::factory()->create();

    createUserCard($other, charizardCard());

    $this->getJson(route('collection.cards.index', ['search' => 'charizard']))
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

test('a search with no match holds no cards', function () {
    createUserCard($this->user);

    $this->getJson(route('collection.cards.index', ['search' => 'nothing here']))
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

test('the collection search treats wildcards as literal characters', function () {
    $percent = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'percent',
        'name' => '100% Charizard',
    ]));

    $underscore = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'underscore',
        'name' => 'a_b',
    ]));

    createUserCard($this->user, createCard(attributes: [
        'external_id' => 'plain',
        'name' => 'Pidgeot ex',
    ]));

    $this->getJson(route('collection.cards.index', ['search' => '100%']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $percent->id);

    $this->getJson(route('collection.cards.index', ['search' => '%']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $percent->id);

    $this->getJson(route('collection.cards.index', ['search' => '_']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $underscore->id);
});

test('the collection can be sorted by card name', function () {
    $pidgeot = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'sv8pt5-3',
        'name' => 'Pidgeot ex',
    ]));

    $charizard = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'sv8pt5-2',
        'name' => 'Charizard ex',
    ]));

    $this->getJson(route('collection.cards.index', ['sort' => 'name']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $charizard->id)
        ->assertJsonPath('data.1.id', $pidgeot->id);
});

test('the collection can be sorted by price and quantity', function () {
    $cheap = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'cheap',
    ]), [
        'market_price' => 1.00,
        'quantity' => 5,
    ]);

    $dear = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'dear',
    ]), [
        'market_price' => 50.00,
        'quantity' => 1,
    ]);

    $this->getJson(route('collection.cards.index', ['sort' => 'price-high']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $dear->id)
        ->assertJsonPath('data.1.id', $cheap->id);

    $this->getJson(route('collection.cards.index', ['sort' => 'price-low']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $cheap->id)
        ->assertJsonPath('data.1.id', $dear->id);

    $this->getJson(route('collection.cards.index', ['sort' => 'quantity']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $cheap->id)
        ->assertJsonPath('data.1.id', $dear->id);
});

test('unpriced cards sort last in both price directions', function () {
    $unpriced = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'unpriced',
    ]), [
        'market_price' => null,
    ]);

    $priced = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'priced',
    ]), [
        'market_price' => 1.00,
    ]);

    $this->getJson(route('collection.cards.index', ['sort' => 'price-low']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $priced->id)
        ->assertJsonPath('data.1.id', $unpriced->id);

    $this->getJson(route('collection.cards.index', ['sort' => 'price-high']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $priced->id)
        ->assertJsonPath('data.1.id', $unpriced->id);
});

test('an unknown sort is rejected', function () {
    $this->getJson(route('collection.cards.index', ['sort' => 'nonsense']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('sort');
});

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

test('the summary counts quantities, unique cards and sets', function () {
    $set = createSet();

    createUserCard($this->user, createCard($set), [
        'quantity' => 3,
        'market_price' => 2.00,
    ]);

    createUserCard($this->user, createCard($set), [
        'quantity' => 2,
        'market_price' => 5.00,
    ]);

    createUserCard($this->user, createCard(createSet()), [
        'quantity' => 1,
        'market_price' => 10.00,
    ]);

    $this->getJson(route('collection.cards.index'))
        ->assertOk()
        ->assertJsonPath('summary.total_cards', 6)
        ->assertJsonPath('summary.unique_cards', 3)
        ->assertJsonPath('summary.sets_collected', 2)
        ->assertJsonPath('summary.estimated_value', 26)
        ->assertJsonPath('summary.portfolio_value', 26);
});

test('cards without a market price contribute nothing to the value', function () {
    createUserCard($this->user, createCard(), [
        'quantity' => 4,
        'market_price' => null,
    ]);

    $this->getJson(route('collection.cards.index'))
        ->assertOk()
        ->assertJsonPath('summary.total_cards', 4)
        ->assertJsonPath('summary.estimated_value', 0)
        ->assertJsonPath('summary.portfolio_value', 0);
});

test('the summary follows the search but not the page', function () {
    createUserCard($this->user, charizardCard(), [
        'quantity' => 2,
        'market_price' => 3.00,
    ]);

    foreach (range(1, 8) as $index) {
        createUserCard($this->user, createCard(attributes: [
            'external_id' => 'filler-'.$index,
            'name' => 'Filler ex',
        ]));
    }

    $this->getJson(route('collection.cards.index', [
        'search' => 'charizard',
        'per_page' => 12,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('summary.total_cards', 2)
        ->assertJsonPath('summary.unique_cards', 1)
        ->assertJsonPath('summary.portfolio_value', 6);
});

test('the summary never counts another user cards', function () {
    $other = User::factory()->create();

    createUserCard($this->user, charizardCard(), ['quantity' => 1]);
    createUserCard($other, createCard(), ['quantity' => 50]);

    $this->getJson(route('collection.cards.index'))
        ->assertOk()
        ->assertJsonPath('summary.total_cards', 1)
        ->assertJsonPath('summary.portfolio_value', 0);
});
