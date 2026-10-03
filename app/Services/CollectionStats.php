<?php

namespace App\Services;

use App\Models\UserCard;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregate the headline numbers for a collection query.
 *
 * The counts and sums are pushed into the database so a large collection is
 * never loaded into memory just to be reduced. The query passed in is
 * expected to select from `user_cards` and must not already join `cards`,
 * since the set count needs that join applied here.
 */
class CollectionStats
{
    /**
     * The aggregates a collection summary is built from.
     *
     * @param  Builder<UserCard>  $query
     * @return array{total_cards: int, unique_cards: int, sets_collected: int, estimated_value: float}
     */
    public function summarize(Builder $query): array
    {
        $row = (clone $query)
            ->toBase()
            ->leftJoin('cards', 'cards.id', '=', 'user_cards.card_id')
            ->selectRaw('COALESCE(SUM(user_cards.quantity), 0) AS total_cards')
            ->selectRaw('COUNT(DISTINCT user_cards.card_id) AS unique_cards')
            ->selectRaw('COUNT(DISTINCT cards.set_id) AS sets_collected')
            ->selectRaw(
                'COALESCE(SUM(user_cards.quantity * COALESCE(user_cards.market_price, 0)), 0) AS estimated_value'
            )
            ->first();

        $totals = $row === null ? [] : (array) $row;

        return [
            'total_cards' => (int) ($totals['total_cards'] ?? 0),
            'unique_cards' => (int) ($totals['unique_cards'] ?? 0),
            'sets_collected' => (int) ($totals['sets_collected'] ?? 0),
            'estimated_value' => round((float) ($totals['estimated_value'] ?? 0), 2),
        ];
    }

    /**
     * The total value of every copy the query matches.
     *
     * Kept separate from {@see self::summarize()} because it is the one
     * number that must not be derived from the page of rows being shown:
     * summing the current page would make the total depend on the page size.
     *
     * It is still driven by the same query, so it narrows with an active
     * search like the summary beside it does, and stays fixed while the user
     * pages or re-sorts. That keeps the figure consistent with the list it is
     * displayed above.
     *
     * @param  Builder<UserCard>  $query
     */
    public function portfolioValue(Builder $query): float
    {
        $row = (clone $query)
            ->toBase()
            ->selectRaw(
                'COALESCE(SUM(quantity * COALESCE(market_price, 0)), 0) AS portfolio_value'
            )
            ->first();

        $totals = $row === null ? [] : (array) $row;

        return round((float) ($totals['portfolio_value'] ?? 0), 2);
    }
}
