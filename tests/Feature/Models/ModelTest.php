<?php

use App\Models\Card;
use App\Models\Set;
use App\Models\User;
use App\Models\UserCard;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Relationships
|--------------------------------------------------------------------------
*/

test('a card belongs to a set', function () {
    $set = createSet();

    expect(createCard($set)->set->is($set))->toBeTrue();
});

test('a set has many cards', function () {
    $set = createSet();

    $first = createCard($set, ['external_id' => 'a', 'name' => 'Charizard ex']);
    $second = createCard($set, ['external_id' => 'b', 'name' => 'Pidgeot ex']);

    expect($set->cards->modelKeys())->toEqualCanonicalizing([$first->id, $second->id]);
});

test('a card has many user cards', function () {
    $user = User::factory()->create();
    $card = createCard();

    $first = createUserCard($user, $card);
    $second = createUserCard($user, $card, ['condition' => 'Damaged']);

    expect($card->userCards->modelKeys())->toEqualCanonicalizing([$first->id, $second->id]);
});

test('a user card belongs to a user and a card', function () {
    $user = User::factory()->create();
    $card = createCard();

    $userCard = createUserCard($user, $card);

    expect($userCard->user->is($user))->toBeTrue()
        ->and($userCard->card->is($card))->toBeTrue();
});

test('a user has many user cards', function () {
    $user = User::factory()->create();
    $card = createCard();

    createUserCard($user, $card);
    createUserCard($user, $card, ['condition' => 'Damaged']);

    expect($user->userCards)->toHaveCount(2);
});

test('the card can be reached through its set and owner', function () {
    $user = User::factory()->create();
    $set = createSet();
    $card = createCard($set);

    createUserCard($user, $card);

    $userCard = $user->userCards->sole();

    expect($userCard->card->set->is($set))->toBeTrue()
        ->and($userCard->card->userCards->modelKeys())->toBe([$userCard->id]);
});

/*
|--------------------------------------------------------------------------
| Casts
|--------------------------------------------------------------------------
*/

test('a user card casts its money columns to two decimals', function () {
    $userCard = new UserCard([
        'purchase_price' => 4.5,
        'market_price' => 12.3,
    ]);

    expect($userCard->purchase_price)->toBe('4.50')
        ->and($userCard->market_price)->toBe('12.30');
});

test('a user card casts its dates', function () {
    $userCard = createUserCard(attributes: [
        'purchase_date' => '2024-03-01',
        'market_price_updated_at' => '2024-03-02 10:30:00',
    ]);

    expect($userCard->purchase_date)->toBeInstanceOf(CarbonInterface::class)
        ->and($userCard->purchase_date->toDateString())->toBe('2024-03-01')
        ->and($userCard->market_price_updated_at->toDateTimeString())->toBe('2024-03-02 10:30:00');
});

test('nullable user card columns stay null', function () {
    $userCard = createUserCard();

    expect($userCard->purchase_price)->toBeNull()
        ->and($userCard->purchase_date)->toBeNull()
        ->and($userCard->market_price)->toBeNull()
        ->and($userCard->market_price_updated_at)->toBeNull()
        ->and($userCard->notes)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Persistence rules
|--------------------------------------------------------------------------
*/

test('a user may only hold one copy per card condition and language', function () {
    $user = User::factory()->create();
    $card = createCard();

    createUserCard($user, $card, [
        'condition' => 'Near Mint',
        'language' => 'English',
    ]);

    expect(fn () => createUserCard($user, $card, [
        'condition' => 'Near Mint',
        'language' => 'English',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('a user card falls back to the default condition', function () {
    $userCard = UserCard::create([
        'user_id' => User::factory()->create()->id,
        'card_id' => createCard()->id,
        'quantity' => 1,
        'language' => 'English',
    ]);

    expect($userCard->condition)->toBe(UserCard::DEFAULT_CONDITION);
});

test('a user card cannot store a missing condition', function () {
    $user = User::factory()->create();
    $card = createCard();

    expect(fn () => DB::table('user_cards')->insert([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'quantity' => 1,
        'condition' => null,
        'language' => 'English',
    ]))->toThrow(QueryException::class);
});

test('a missing condition can no longer duplicate a collection row', function () {
    $user = User::factory()->create();
    $card = createCard();

    /*
     * NULLs are distinct under a unique index, so the nullable column
     * allowed the same card to be stored twice with no grade recorded.
     * With the column required, the index enforces one row per card.
     */
    createUserCard($user, $card, [
        'condition' => UserCard::DEFAULT_CONDITION,
        'language' => 'English',
    ]);

    expect(fn () => DB::table('user_cards')->insert([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'quantity' => 1,
        'condition' => null,
        'language' => 'English',
    ]))->toThrow(QueryException::class);
});

test('deleting a set deletes its cards and user cards', function () {
    $user = User::factory()->create();
    $set = createSet();

    createUserCard($user, createCard($set));

    $set->delete();

    expect(Card::count())->toBe(0)
        ->and(UserCard::count())->toBe(0);
});

test('deleting a user deletes their collection but not the cards', function () {
    $user = User::factory()->create();
    $card = createCard();

    createUserCard($user, $card);

    $user->delete();

    expect(UserCard::count())->toBe(0)
        ->and(Card::count())->toBe(1)
        ->and(Set::count())->toBe(1);
});

test('card and set external ids are unique', function () {
    createCard(attributes: ['external_id' => 'sv8pt5-2']);

    expect(fn () => createCard(attributes: ['external_id' => 'sv8pt5-2']))
        ->toThrow(UniqueConstraintViolationException::class);

    createSet(['external_id' => 'sv3pt5']);

    expect(fn () => createSet(['external_id' => 'sv3pt5']))
        ->toThrow(UniqueConstraintViolationException::class);
});
