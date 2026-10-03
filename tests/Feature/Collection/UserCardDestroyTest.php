<?php

use App\Models\User;
use App\Models\UserCard;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->userCard = createUserCard($this->user);

    $this->actingAs($this->user);
});

test('guests cannot remove a collection card', function () {
    auth()->logout();

    $this->delete(route('collection.cards.destroy', $this->userCard))
        ->assertRedirect(route('login'));

    expect(UserCard::whereKey($this->userCard->id)->exists())->toBeTrue();
});

test("a user cannot remove another user's collection card", function () {
    $this->actingAs(User::factory()->create())
        ->delete(route('collection.cards.destroy', $this->userCard))
        ->assertNotFound();

    expect(UserCard::whereKey($this->userCard->id)->exists())->toBeTrue();
});

test('removing a card that does not exist is a 404', function () {
    $this->delete(route('collection.cards.destroy', 9999))
        ->assertNotFound();
});

test('a collection card is removed', function () {
    $this->deleteJson(route('collection.cards.destroy', $this->userCard))
        ->assertOk()
        ->assertJsonPath('message', 'Card removed from your collection.');

    expect(UserCard::whereKey($this->userCard->id)->exists())->toBeFalse();
});

test('only the targeted collection card is removed', function () {
    $kept = createUserCard($this->user, createCard(attributes: [
        'external_id' => 'sv8pt5-3',
        'name' => 'Pidgeot ex',
    ]));

    $this->deleteJson(route('collection.cards.destroy', $this->userCard))
        ->assertOk();

    expect(UserCard::whereKey($this->userCard->id)->exists())->toBeFalse()
        ->and(UserCard::whereKey($kept->id)->exists())->toBeTrue();
});

test('the shared card is kept when one user removes it from their collection', function () {
    $other = User::factory()->create();
    $otherCard = createUserCard($other, $this->userCard->card);

    $this->deleteJson(route('collection.cards.destroy', $this->userCard))
        ->assertOk();

    expect(UserCard::whereKey($otherCard->id)->exists())->toBeTrue()
        ->and($otherCard->card->exists())->toBeTrue()
        ->and($otherCard->card->set->exists())->toBeTrue();
});
