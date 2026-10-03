<?php

use Illuminate\Support\Facades\Http;

test('the root layout exposes a csrf token', function () {
    Http::fake([
        'api.tcgdex.net/*' => Http::response([]),
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();

    /*
     * The add-to-collection form reads this meta tag and sends it as the
     * X-CSRF-TOKEN header, so its absence breaks every collection write.
     */
    $response->assertSee('name="csrf-token"', false);
    $response->assertSee(csrf_token(), false);
});
