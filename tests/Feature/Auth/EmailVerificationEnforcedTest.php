<?php

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/*
|--------------------------------------------------------------------------
| Email verification
|--------------------------------------------------------------------------
|
| The `verified` middleware short-circuits for any user that does not
| implement MustVerifyEmail, so it silently protects nothing unless the
| contract is in place. These tests pin both halves together: the model must
| satisfy the contract, and the middleware must actually block a request.
|
*/

test('the user model satisfies the must verify email contract', function () {
    expect(User::class)->toImplement(
        MustVerifyEmail::class
    );
});

test('an unverified user is redirected away from the dashboard', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('an unverified user is redirected away from the collection', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('collection.index'))
        ->assertRedirect(route('verification.notice'));
});

test('an unverified user cannot read collection cards', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->getJson(route('collection.cards.index'))
        ->assertForbidden();
});

test('a verified user reaches the dashboard', function () {
    $this->withoutVite();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk();
});
