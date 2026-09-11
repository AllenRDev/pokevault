<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Set;
use App\Models\UserCard;
use App\Services\TcgDexCardMapper;
use App\Services\TcgDexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserCardController extends Controller
{
    public function __construct(
        private TcgDexService $tcgDexService,
        private TcgDexCardMapper $cardMapper,
    ) {
    }

    /**
     * Get the authenticated user's cards.
     */
    public function index(Request $request): JsonResponse
    {
        $userCards = $request->user()
            ->userCards()
            ->with('card.set')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($userCards);
    }

    /**
     * Add a TCGdex card to the user's collection.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'external_id' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'condition' => [
                'required',
                'string',
                Rule::in([
                    'Near Mint',
                    'Lightly Played',
                    'Moderately Played',
                    'Heavily Played',
                    'Damaged',
                ]),
            ],
            'language' => [
                'required',
                'string',
                Rule::in([
                    'English',
                    'Japanese',
                    'Korean',
                    'Chinese',
                    'French',
                    'German',
                    'Italian',
                    'Spanish',
                    'Portuguese',
                ]),
            ],
            'purchase_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /*
         * Get the full card from TCGdex so the card and
         * its set can be stored locally.
         */
        $cardData = $this->tcgDexService->getCard(
            $validated['external_id']
        );

        $set = Set::updateOrCreate(
            [
                'external_id' => $cardData['set']['external_id'],
            ],
            [
                'name' => $cardData['set']['name'],
                'series' => $cardData['set']['series'] ?? null,
                'printed_total' => $cardData['set']['printed_total'] ?? null,
                'release_date' => $cardData['set']['release_date'] ?? null,
                'logo_url' => $cardData['set']['logo_url'] ?? null,
                'symbol_url' => $cardData['set']['symbol_url'] ?? null,
            ]
        );

        $card = Card::updateOrCreate(
            [
                'external_id' => $cardData['external_id'],
            ],
            [
                'set_id' => $set->id,
                'name' => $cardData['name'],
                'number' => $cardData['number'],
                'rarity' => $cardData['rarity'] ?? null,
                'image_small' => $cardData['image_small'] ?? null,
                'image_large' => $cardData['image_large'] ?? null,
            ]
        );

        /*
         * Get the current TCGplayer market price.
         */
        $marketPrice = $this->cardMapper->getMarketPrice($cardData);

        $marketPriceUpdatedAt = $marketPrice !== null
            ? now()
            : null;

        /*
         * If the user already owns this card with the same
         * condition/language, increase the quantity.
         */
        $userCard = UserCard::where([
            'user_id' => $request->user()->id,
            'card_id' => $card->id,
            'condition' => $validated['condition'],
            'language' => $validated['language'],
        ])->first();

        if ($userCard) {
            $userCard->quantity += $validated['quantity'];

            if (array_key_exists('purchase_price', $validated)) {
                $userCard->purchase_price =
                    $validated['purchase_price'];
            }

            if (array_key_exists('purchase_date', $validated)) {
                $userCard->purchase_date =
                    $validated['purchase_date'];
            }

            if (array_key_exists('notes', $validated)) {
                $userCard->notes =
                    $validated['notes'];
            }

            $userCard->market_price = $marketPrice;
            $userCard->market_price_updated_at =
                $marketPriceUpdatedAt;

            $userCard->save();
        } else {
            $userCard = UserCard::create([
                'user_id' => $request->user()->id,
                'card_id' => $card->id,
                'quantity' => $validated['quantity'],
                'condition' => $validated['condition'],
                'language' => $validated['language'],
                'purchase_price' =>
                    $validated['purchase_price'] ?? null,
                'purchase_date' =>
                    $validated['purchase_date'] ?? null,
                'notes' =>
                    $validated['notes'] ?? null,
                'market_price' => $marketPrice,
                'market_price_updated_at' =>
                    $marketPriceUpdatedAt,
            ]);
        }

        $userCard->load('card.set');

        return response()->json([
            'message' => 'Card added to your collection.',
            'user_card' => $userCard,
        ], 201);
    }

    /**
     * Update a card in the user's collection.
     */
    public function update(
        Request $request,
        UserCard $userCard
    ): JsonResponse {
        abort_unless(
            $userCard->user_id === $request->user()->id,
            404
        );

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'condition' => [
                'required',
                'string',
                Rule::in([
                    'Near Mint',
                    'Lightly Played',
                    'Moderately Played',
                    'Heavily Played',
                    'Damaged',
                ]),
            ],
            'language' => [
                'required',
                'string',
                Rule::in([
                    'English',
                    'Japanese',
                    'Korean',
                    'Chinese',
                    'French',
                    'German',
                    'Italian',
                    'Spanish',
                    'Portuguese',
                ]),
            ],
            'purchase_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $userCard->update($validated);
        $userCard->load('card.set');

        return response()->json([
            'message' => 'Collection card updated.',
            'user_card' => $userCard,
        ]);
    }

    /**
     * Remove a card from the user's collection.
     */
    public function destroy(
        Request $request,
        UserCard $userCard
    ): JsonResponse {
        abort_unless(
            $userCard->user_id === $request->user()->id,
            404
        );

        $userCard->delete();

        return response()->json([
            'message' => 'Card removed from your collection.',
        ]);
    }
}