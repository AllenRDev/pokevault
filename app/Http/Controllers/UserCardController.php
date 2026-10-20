<?php

namespace App\Http\Controllers;

use App\Exceptions\CardNotFoundException;
use App\Http\Requests\StoreUserCardRequest;
use App\Models\Card;
use App\Models\Set;
use App\Models\UserCard;
use App\Services\CollectionStats;
use App\Services\TcgDexCardMapper;
use App\Services\TcgDexService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserCardController extends Controller
{
    /**
     * The orderings the collection listing accepts.
     *
     * @var list<string>
     */
    public const SORTS = [
        'recent',
        'name',
        'price-high',
        'price-low',
        'quantity',
    ];

    /**
     * How many collection cards a page holds when none is requested.
     */
    public const DEFAULT_PER_PAGE = 24;

    public function __construct(
        private TcgDexService $tcgDexService,
        private TcgDexCardMapper $cardMapper,
    ) {}

    /**
     * Get a page of the authenticated user's cards.
     *
     * Searching, sorting, and paging happen in the database so the response
     * stays a fixed size no matter how large the collection grows. The
     * summary covers the current search but ignores paging and sorting, so
     * the header totals do not change as the user moves between pages.
     */
    public function index(Request $request, CollectionStats $stats): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:12', 'max:96'],
        ]);

        $sort = $validated['sort'] ?? 'recent';

        $query = $this->collectionQuery($request, $validated['search'] ?? null);

        $summary = [
            ...$stats->summarize(clone $query),
            'portfolio_value' => $stats->portfolioValue(clone $query),
        ];

        $this->applySort($query, $sort);

        $paginator = $query
            ->with('card.set')
            ->paginate((int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE))
            ->withQueryString();

        return response()->json([
            'data' => $paginator->items(),
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * The base listing query, narrowed to a search term when one is given.
     *
     * The filter is applied through `whereHas` so it compiles to a subquery
     * rather than a join; that leaves `cards` free to be joined later by
     * {@see self::applySort()} without colliding with the aggregates.
     *
     * @return Builder<UserCard>
     */
    private function collectionQuery(Request $request, ?string $search): Builder
    {
        $query = $request->user()->userCards()->getQuery();

        $term = trim((string) $search);

        if ($term === '') {
            return $query;
        }

        /*
         * `!` is used as the escape character because an explicit ESCAPE
         * clause is required: SQLite does not treat a backslash as an escape
         * by default, so escaping with one would make a literal `%` or `_`
         * search behave differently there than on Postgres or MySQL.
         */
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
        $needle = "%{$escaped}%";

        return $query->whereHas('card', function (Builder $card) use ($needle): void {
            $card->whereRaw("cards.name LIKE ? ESCAPE '!'", [$needle])
                ->orWhereRaw("cards.number LIKE ? ESCAPE '!'", [$needle])
                ->orWhereHas('set', function (Builder $set) use ($needle): void {
                    $set->whereRaw("sets.name LIKE ? ESCAPE '!'", [$needle]);
                });
        });
    }

    /**
     * Order the listing, keeping the sort stable across pages.
     *
     * Sorting by card name is the only option that needs the `cards` table in
     * the query, so the join is applied here rather than up front.
     *
     * @param  Builder<UserCard>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        if ($sort === 'name') {
            $query->leftJoin('cards', 'cards.id', '=', 'user_cards.card_id')
                ->select('user_cards.*')
                ->orderBy('cards.name')
                ->orderBy('user_cards.id');

            return;
        }

        /*
         * Unpriced cards sort last in both directions. Without the explicit
         * null check SQLite puts NULL first ascending and Postgres puts it
         * first descending, so "cheapest" would surface cards with no price.
         */
        match ($sort) {
            'price-high' => $query
                ->orderByRaw('user_cards.market_price IS NULL')
                ->orderByDesc('user_cards.market_price'),
            'price-low' => $query
                ->orderByRaw('user_cards.market_price IS NULL')
                ->orderBy('user_cards.market_price'),
            'quantity' => $query->orderByDesc('user_cards.quantity'),
            default => $query->orderByDesc('user_cards.created_at'),
        };

        $query->orderByDesc('user_cards.id');
    }

    /**
     * Add a TCGdex card to the user's collection.
     */
    public function store(StoreUserCardRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /*
         * Get the full card from TCGdex so the card and
         * its set can be stored locally. The read cache is
         * bypassed so the price recorded below is current
         * rather than up to a day old.
         */
        try {
            $cardData = $this->tcgDexService->getCard(
                $validated['external_id'],
                fresh: true
            );
        } catch (CardNotFoundException) {
            abort(404, 'Card not found.');
        }

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

        /*
         * If the user already owns this card with the same condition and
         * language, increase the quantity. The lookup and insert run in one
         * transaction so concurrent submissions cannot both decide the row
         * is missing; if they still race, the unique index rejects the loser
         * and it is folded into the row the winner created.
         */
        $attributes = [
            'user_id' => $request->user()->id,
            'card_id' => $card->id,
            'condition' => $validated['condition'],
            'language' => $validated['language'],
        ];

        try {
            $userCard = DB::transaction(function () use (
                $attributes,
                $validated,
                $marketPrice
            ): UserCard {
                $existing = UserCard::query()
                    ->where($attributes)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $this->mergeInto(
                        $existing,
                        $validated,
                        $marketPrice
                    );
                }

                return UserCard::create([
                    ...$attributes,
                    'quantity' => $validated['quantity'],
                    'purchase_price' => $validated['purchase_price'] ?? null,
                    'purchase_date' => $validated['purchase_date'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'market_price' => $marketPrice,
                    'market_price_updated_at' => $marketPrice !== null ? now() : null,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $userCard = $this->mergeInto(
                UserCard::query()->where($attributes)->firstOrFail(),
                $validated,
                $marketPrice
            );
        }

        $userCard->load('card.set');

        return response()->json([
            'message' => 'Card added to your collection.',
            'user_card' => $userCard,
        ], 201);
    }

    /**
     * Fold an incoming quantity into an existing collection entry.
     *
     * The incoming quantity is validated, but it is bounded again here so
     * the stored total can neither be pushed past the ceiling nor go
     * negative.
     *
     * @param  array<string, mixed>  $validated
     */
    private function mergeInto(
        UserCard $userCard,
        array $validated,
        ?float $marketPrice
    ): UserCard {
        $incoming = max(
            1,
            min(UserCard::MAX_QUANTITY, (int) $validated['quantity'])
        );

        $userCard->quantity = min(
            UserCard::MAX_QUANTITY,
            $userCard->quantity + $incoming
        );

        foreach (['purchase_price', 'purchase_date', 'notes'] as $field) {
            if (array_key_exists($field, $validated)) {
                $userCard->{$field} = $validated[$field];
            }
        }

        $userCard->market_price = $marketPrice;
        $userCard->market_price_updated_at = $marketPrice !== null ? now() : null;

        $userCard->save();

        return $userCard;
    }

    /**
     * Update a card in the user's collection.
     */
    public function update(
        StoreUserCardRequest $request,
        UserCard $userCard
    ): JsonResponse {
        abort_unless(
            $userCard->user_id === $request->user()->id,
            404
        );

        $validated = $request->validated();

        /*
         * `condition` and `language` are part of the unique index over
         * (user_id, card_id, condition, language), so repointing an entry at
         * a grade the user already owns would otherwise surface as a 500
         * from the database rather than a validation error.
         */
        $duplicate = UserCard::query()
            ->where('user_id', $request->user()->id)
            ->where('card_id', $userCard->card_id)
            ->where('condition', $validated['condition'])
            ->where('language', $validated['language'])
            ->where('id', '!=', $userCard->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'condition' => 'You already have this card in that condition and language.',
            ]);
        }

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
