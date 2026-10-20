<?php

use App\Models\User;
use App\Models\UserCard;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->userCard = createUserCard($this->user, charizardCard());

    $this->actingAs($this->user);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function updatePayload(array $overrides = []): array
{
    return array_merge([
        'quantity' => 1,
        'condition' => 'Near Mint',
        'language' => 'English',
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

test('guests cannot update a collection card', function () {
    auth()->logout();

    $this->put(route('collection.cards.update', $this->userCard), updatePayload([
        'quantity' => 9,
    ]))->assertRedirect(route('login'));

    expect($this->userCard->refresh()->quantity)->toBe(1);
});

test("a user cannot update another user's collection card", function () {
    $this->actingAs(User::factory()->create())
        ->put(route('collection.cards.update', $this->userCard), updatePayload([
            'quantity' => 9,
        ]))
        ->assertNotFound();

    expect($this->userCard->refresh()->quantity)->toBe(1);
});

test('updating a card that does not exist is a 404', function () {
    $this->put(route('collection.cards.update', 9999), updatePayload())
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Updates
|--------------------------------------------------------------------------
*/

test('a collection card is updated', function () {
    $this->putJson(route('collection.cards.update', $this->userCard), updatePayload([
        'quantity' => 7,
        'condition' => 'Lightly Played',
        'language' => 'Japanese',
        'purchase_price' => 4.25,
        'purchase_date' => '2024-03-01',
        'notes' => 'Traded at a convention',
    ]))
        ->assertOk()
        ->assertJsonPath('message', 'Collection card updated.')
        ->assertJsonPath('user_card.quantity', 7)
        ->assertJsonPath('user_card.condition', 'Lightly Played')
        ->assertJsonPath('user_card.language', 'Japanese')
        ->assertJsonPath('user_card.notes', 'Traded at a convention');
});

test('the updated values are persisted', function () {
    $this->putJson(route('collection.cards.update', $this->userCard), updatePayload([
        'quantity' => 7,
        'purchase_price' => 4.25,
        'purchase_date' => '2024-03-01',
        'notes' => 'Traded at a convention',
    ]))->assertOk();

    $userCard = $this->userCard->refresh();

    expect($userCard->quantity)->toBe(7)
        ->and((float) $userCard->purchase_price)->toBe(4.25)
        ->and($userCard->purchase_date->toDateString())->toBe('2024-03-01')
        ->and($userCard->notes)->toBe('Traded at a convention');
});

test('optional values can be cleared', function () {
    $userCard = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'sv8pt5-9',
        'name' => 'Froakie ex',
    ]), [
        'purchase_price' => 4.25,
        'notes' => 'Traded at a convention',
    ]);

    $this->putJson(route('collection.cards.update', $userCard), updatePayload([
        'purchase_price' => null,
        'notes' => null,
    ]))->assertOk();

    $userCard->refresh();

    expect($userCard->purchase_price)->toBeNull()
        ->and($userCard->notes)->toBeNull();
});

test('the updated card is returned with its card and set', function () {
    $this->putJson(route('collection.cards.update', $this->userCard), updatePayload([
        'quantity' => 3,
    ]))
        ->assertOk()
        ->assertJsonPath('user_card.card.external_id', 'sv8pt5-2')
        ->assertJsonPath('user_card.card.set.external_id', 'sv8pt5');
});

test('the market price is left untouched by an update', function () {
    $userCard = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'sv8pt5-8',
        'name' => 'Mewtwo ex',
    ]), [
        'market_price' => 12.34,
        'market_price_updated_at' => now(),
    ]);

    $this->putJson(route('collection.cards.update', $userCard), updatePayload([
        'quantity' => 3,
        'market_price' => 99.99,
    ]))->assertOk();

    expect((float) $userCard->refresh()->market_price)->toBe(12.34);
});

test('a card cannot be pointed at a different card', function () {
    $otherCard = createCard(attributes: ['external_id' => 'sv8pt5-7', 'name' => 'Mewtwo ex']);

    $this->putJson(route('collection.cards.update', $this->userCard), updatePayload([
        'card_id' => $otherCard->id,
        'user_id' => User::factory()->create()->id,
    ]))->assertOk();

    $userCard = $this->userCard->refresh();

    expect($userCard->card_id)->not->toBe($otherCard->id)
        ->and($userCard->user_id)->toBe($this->user->id);
});

test('a card cannot be moved onto an existing condition and language', function () {
    $lightlyPlayed = createUserCard($this->user, $this->userCard->card, [
        'condition' => 'Lightly Played',
        'language' => 'English',
        'quantity' => 3,
    ]);

    $this->putJson(route('collection.cards.update', $lightlyPlayed), updatePayload([
        'quantity' => 2,
        'condition' => 'Near Mint',
        'language' => 'English',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('condition');

    expect($lightlyPlayed->refresh())
        ->condition->toBe('Lightly Played')
        ->quantity->toBe(3)
        ->and(UserCard::query()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('the quantity and condition are required', function () {
    $this->putJson(route('collection.cards.update', $this->userCard), [
        'language' => 'English',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['quantity', 'condition']);
});

test('the quantity must stay between one and 999', function () {
    $this->putJson(route('collection.cards.update', $this->userCard), updatePayload([
        'quantity' => 0,
    ]))->assertStatus(422)->assertJsonValidationErrors('quantity');

    $this->putJson(route('collection.cards.update', $this->userCard), updatePayload([
        'quantity' => 1000,
    ]))->assertStatus(422)->assertJsonValidationErrors('quantity');

    expect($this->userCard->refresh()->quantity)->toBe(1);
});

test('the condition and language must be supported values', function () {
    $this->putJson(route('collection.cards.update', $this->userCard), updatePayload([
        'condition' => 'Mint',
    ]))->assertStatus(422)->assertJsonValidationErrors('condition');

    $this->putJson(route('collection.cards.update', $this->userCard), updatePayload([
        'language' => 'Elvish',
    ]))->assertStatus(422)->assertJsonValidationErrors('language');

    expect($this->userCard->refresh())
        ->condition->toBe('Near Mint')
        ->language->toBe('English');
});

test('a rejected update changes nothing', function () {
    UserCard::query()->whereKey($this->userCard->id)->update([
        'notes' => 'original note',
    ]);

    $this->putJson(route('collection.cards.update', $this->userCard), updatePayload([
        'quantity' => 5,
        'notes' => 'updated note',
        'purchase_price' => -3,
    ]))->assertStatus(422);

    $userCard = $this->userCard->refresh();

    expect($userCard->quantity)->toBe(1)
        ->and($userCard->notes)->toBe('original note');
});
