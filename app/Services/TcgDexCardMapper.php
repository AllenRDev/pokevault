<?php

namespace App\Services;

class TcgDexCardMapper
{
    /**
     * Map a lightweight TCGdex card result.
     *
     * Keys are read defensively: the service hands over whatever the upstream
     * payload contained, so a card missing an id or name degrades to an empty
     * string rather than emitting a warning and persisting a null.
     *
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    public function mapBrief(array $card): array
    {
        $image = $this->toImageUrl($card['image'] ?? null);

        return [
            'external_id' => (string) ($card['id'] ?? ''),
            'name' => (string) ($card['name'] ?? ''),
            'number' => (string) ($card['localId'] ?? ''),
            'rarity' => null,
            'image_small' => $image ? "{$image}/low.webp" : null,
            'image_large' => $image ? "{$image}/high.webp" : null,
            'set' => null,
        ];
    }

    /**
     * Map a full TCGdex card for the card details page.
     *
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    public function map(array $card): array
    {
        $image = $this->toImageUrl($card['image'] ?? null);
        $set = is_array($card['set'] ?? null) ? $card['set'] : [];

        return [
            'external_id' => (string) ($card['id'] ?? ''),
            'name' => (string) ($card['name'] ?? ''),
            'number' => (string) ($card['localId'] ?? ''),
            'rarity' => $card['rarity'] ?? null,

            'image_small' => $image
                ? "{$image}/low.webp"
                : null,

            'image_large' => $image
                ? "{$image}/high.webp"
                : null,

            'prices' => $this->mapPricing(
                is_array($card['pricing'] ?? null)
                    ? $card['pricing']
                    : null
            ),

            'set' => [
                'external_id' => (string) ($set['id'] ?? ''),
                'name' => (string) ($set['name'] ?? ''),
                'series' => $this->toStringOrNull(
                    $set['serie']['name'] ?? null
                ),
                'printed_total' => $this->toIntOrNull(
                    $set['cardCount']['official'] ?? null
                ),
                'release_date' => $this->toStringOrNull(
                    $set['releaseDate'] ?? null
                ),

                'logo_url' => $this->mapSetImage($set['logo'] ?? null),

                'symbol_url' => $this->mapSetImage($set['symbol'] ?? null),
            ],
        ];
    }

    /**
     * Reduce an upstream image reference to a non-empty string.
     *
     * Upstream occasionally sends an array or a number here, which would
     * otherwise be interpolated into a broken URL.
     */
    private function toImageUrl(mixed $image): ?string
    {
        if (is_string($image)) {
            return $image !== '' ? $image : null;
        }

        if (is_int($image) || is_float($image)) {
            return (string) $image;
        }

        return null;
    }

    private function toStringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function mapSetImage(mixed $image): ?string
    {
        $image = $this->toImageUrl($image);

        if (! $image) {
            return null;
        }

        return "{$image}.webp";
    }

    /**
     * Narrow a nested pricing section to an array.
     *
     * Upstream sections are read with offsets, which would raise an error on
     * a scalar, so each one is confirmed to be an array first.
     *
     * @param  array<string, mixed>  $pricing
     * @return array<string, mixed>
     */
    private function toSection(array $pricing, string $key): array
    {
        $section = $pricing[$key] ?? null;

        return is_array($section) ? $section : [];
    }

    /**
     * @param  array<string, mixed>|null  $pricing
     * @return array<string, mixed>|null
     */
    private function mapPricing(?array $pricing): ?array
    {
        if (! $pricing) {
            return null;
        }

        $tcgplayer = $this->toSection($pricing, 'tcgplayer');
        $cardmarket = $this->toSection($pricing, 'cardmarket');

        return [
            'tcgplayer' => [
                'updated' => $tcgplayer['updated'] ?? null,
                'unit' => $tcgplayer['unit'] ?? 'USD',
                'normal' => $this->mapTcgPlayerVariant(
                    $tcgplayer['normal'] ?? null
                ),
                'holofoil' => $this->mapTcgPlayerVariant(
                    $tcgplayer['holofoil'] ?? null
                ),
                'reverse_holofoil' => $this->mapTcgPlayerVariant(
                    $tcgplayer['reverse-holofoil'] ?? null
                ),
                'first_edition' => $this->mapTcgPlayerVariant(
                    $tcgplayer['1st-edition'] ?? null
                ),
            ],

            'cardmarket' => [
                'updated' => $cardmarket['updated'] ?? null,
                'unit' => $cardmarket['unit'] ?? 'EUR',
                'avg' => $cardmarket['avg'] ?? null,
                'low' => $cardmarket['low'] ?? null,
                'trend' => $cardmarket['trend'] ?? null,
                'avg1' => $cardmarket['avg1'] ?? null,
                'avg7' => $cardmarket['avg7'] ?? null,
                'avg30' => $cardmarket['avg30'] ?? null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapTcgPlayerVariant(mixed $variant): ?array
    {
        if (! is_array($variant) || ! $variant) {
            return null;
        }

        return [
            'low' => $variant['lowPrice'] ?? null,
            'mid' => $variant['midPrice'] ?? null,
            'high' => $variant['highPrice'] ?? null,
            'market' => $variant['marketPrice'] ?? null,
            'direct_low' => $variant['directLowPrice'] ?? null,
        ];
    }

    /**
     * Resolve the best available TCGplayer market price for a mapped card.
     *
     * The normal variant is preferred, then holofoil, reverse holofoil and
     * first edition. Upstream values are not guaranteed to be numeric, so a
     * variant is skipped rather than persisted as a bad price into the
     * market_price decimal column.
     *
     * @param  array<string, mixed>  $cardData
     */
    public function getMarketPrice(array $cardData): ?float
    {
        $variants = $cardData['prices']['tcgplayer'] ?? [];

        foreach (['normal', 'holofoil', 'reverse_holofoil', 'first_edition'] as $variant) {
            $price = $variants[$variant]['market'] ?? null;

            if (is_numeric($price)) {
                return (float) $price;
            }
        }

        return null;
    }

    /**
     * Map multiple lightweight cards.
     *
     * @param  array<array<string, mixed>>  $cards
     * @return list<array<string, mixed>>
     */
    public function mapBriefMany(array $cards): array
    {
        return array_values(array_map(
            fn (array $card) => $this->mapBrief($card),
            $cards
        ));
    }
}
