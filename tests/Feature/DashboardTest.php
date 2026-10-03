<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('an empty collection reports zeroes', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('overall.unique_cards', 0)
            ->where('overall.total_cards', 0)
            ->where('overall.sets_collected', 0)
            ->where('overall.estimated_value', 0),
        );
});

test('the dashboard counts every copy the user owns', function () {
    $user = User::factory()->create();
    $card = createCard();

    createUserCard($user, $card, ['quantity' => 3]);
    createUserCard($user, $card, ['condition' => 'Damaged', 'quantity' => 2]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('overall.unique_cards', 1)
            ->where('overall.total_cards', 5)
            ->where('overall.sets_collected', 1),
        );
});

test('the dashboard counts each set once', function () {
    $user = User::factory()->create();

    $firstSet = createSet(['external_id' => 'sv8pt5', 'name' => 'Obsidian Flames']);
    $secondSet = createSet(['external_id' => 'sv3pt5', 'name' => 'Obsidian Flames']);
    $thirdSet = createSet(['external_id' => 'sv2pt5', 'name' => 'Paldea Evolved']);

    createUserCard($user, createCard($firstSet, ['external_id' => 'a', 'name' => 'Charizard ex']));
    createUserCard($user, createCard($firstSet, ['external_id' => 'b', 'name' => 'Pidgeot ex']));
    createUserCard($user, createCard($secondSet, ['external_id' => 'c', 'name' => 'Froakie ex']));
    createUserCard($user, createCard($thirdSet, ['external_id' => 'd', 'name' => 'Mewtwo ex']));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('overall.unique_cards', 4)
            ->where('overall.sets_collected', 3),
        );
});

test('the estimated value multiplies quantity by market price', function () {
    $user = User::factory()->create();
    $card = createCard();

    createUserCard($user, $card, [
        'quantity' => 2,
        'market_price' => 10.00,
        'market_price_updated_at' => now(),
    ]);

    createUserCard($user, $card, [
        'condition' => 'Lightly Played',
        'quantity' => 1,
        'market_price' => 5.50,
        'market_price_updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('overall.estimated_value', 25.50),
        );
});

test('cards without a market price are valued at zero', function () {
    $user = User::factory()->create();
    $card = createCard();

    createUserCard($user, $card, ['quantity' => 4, 'market_price' => null]);
    createUserCard($user, $card, [
        'condition' => 'Damaged',
        'quantity' => 1,
        'market_price' => 2.25,
        'market_price_updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('overall.estimated_value', 2.25),
        );
});

test('the estimated value is rounded to two decimal places', function () {
    $user = User::factory()->create();

    createUserCard($user, createCard(), [
        'quantity' => 3,
        'market_price' => 0.10,
        'market_price_updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('overall.estimated_value', 0.30),
        );
});

test('the dashboard only summarises the signed in user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    createUserCard($user, createCard(), [
        'quantity' => 1,
        'market_price' => 100,
        'market_price_updated_at' => now(),
    ]);

    createUserCard($other, createCard(attributes: [
        'external_id' => 'other-1',
        'name' => 'Mewtwo ex',
    ]), [
        'quantity' => 9,
        'market_price' => 1,
        'market_price_updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('overall.unique_cards', 1)
            ->where('overall.total_cards', 1)
            ->where('overall.estimated_value', 100),
        );
});

test('the dashboard aggregates in the database', function () {
    $user = User::factory()->create();

    foreach (range(1, 25) as $index) {
        createUserCard($user, createCard(createSet()), ['quantity' => $index]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $statements = array_map(
        fn (array $entry): string => strtolower($entry['query']),
        DB::getQueryLog()
    );

    DB::disableQueryLog();

    $aggregates = array_filter(
        $statements,
        fn (string $sql): bool => str_contains($sql, 'count(distinct')
    );

    /*
     * Counting distinct cards and sets has to happen in SQL. Reducing a
     * loaded collection in PHP produced the same numbers while transferring
     * every row, so a regression here would not fail any value assertion.
     */
    expect($aggregates)->not->toBeEmpty();

    expect(implode(' ', $aggregates))
        ->toContain('user_cards.card_id')
        ->toContain('cards.set_id');
});
