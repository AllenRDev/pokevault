<?php

namespace App\Services;

class TcgDexCardMapper
{
    /**
     * Map a lightweight TCGdex card result.
     */
    public function mapBrief(array $card): array
    {
        $image = $card['image'] ?? null;

        return [
            'external_id' => $card['id'],
            'name' => $card['name'],
            'number' => $card['localId'] ?? '',
            'rarity' => null,
            'image_small' => $image ? "{$image}/low.webp" : null,
            'image_large' => $image ? "{$image}/high.webp" : null,
            'set' => null,
        ];
    }

    /**
     * Map a full TCGdex card for the card details page.
     */
    public function map(array $card): array
    {
        $image = $card['image'] ?? null;

        return [
            'external_id' => $card['id'],
            'name' => $card['name'],
            'number' => $card['localId'] ?? '',
            'rarity' => $card['rarity'] ?? null,
            'image_small' => $image ? "{$image}/low.webp" : null,
            'image_large' => $image ? "{$image}/high.webp" : null,

            'prices' => $this->mapPricing($card['pricing'] ?? null),

            'set' => [
    'external_id' => $card['set']['id'] ?? '',
    'name' => $card['set']['name'] ?? '',
    'series' => $card['set']['serie']['name'] ?? null,
    'printed_total' => $card['set']['cardCount']['official'] ?? null,
    'release_date' => $card['set']['releaseDate'] ?? null,
    'logo_url' => $card['set']['logo'] ? "{$card['set']['logo']}/high.webp" : null,
    'symbol_url' => $card['set']['symbol'] ? "{$card['set']['symbol']}.webp" : null,
],
        ];
    }

    /**
     * Map marketplace pricing while preserving TCGdex's
     * variant-specific pricing structure.
     */
    private function mapPricing(?array $pricing): ?array
    {
        if (!$pricing) {
            return null;
        }

        $result = [];

        if (isset($pricing['tcgplayer'])) {
            $tcgplayer = $pricing['tcgplayer'];

            $result['tcgplayer'] = [
                'updated' => $tcgplayer['updated'] ?? null,
                'unit' => $tcgplayer['unit'] ?? 'USD',

                'normal' => $this->mapTcgPlayerVariant(
                    $tcgplayer['normal'] ?? null
                ),

                'reverse' => $this->mapTcgPlayerVariant(
                    $tcgplayer['reverse'] ?? $tcgplayer['reverse-holofoil'] ?? null
                ),

                'holo' => $this->mapTcgPlayerVariant(
                    $tcgplayer['holofoil'] ?? $tcgplayer['holo'] ?? null
                ),

                'first_edition' => $this->mapTcgPlayerVariant(
                    $tcgplayer['1st-edition'] ?? null
                ),

                'first_edition_holo' => $this->mapTcgPlayerVariant(
                    $tcgplayer['1st-edition-holofoil'] ?? null
                ),

                'unlimited' => $this->mapTcgPlayerVariant(
                    $tcgplayer['unlimited'] ?? null
                ),

                'unlimited_holo' => $this->mapTcgPlayerVariant(
                    $tcgplayer['unlimited-holofoil'] ?? null
                ),
            ];
        }

        if (isset($pricing['cardmarket'])) {
            $cardmarket = $pricing['cardmarket'];

            $result['cardmarket'] = [
                'updated' => $cardmarket['updated'] ?? null,
                'unit' => $cardmarket['unit'] ?? 'EUR',

                'average' => $cardmarket['avg'] ?? null,
                'low' => $cardmarket['low'] ?? null,
                'trend' => $cardmarket['trend'] ?? null,

                'average_1d' => $cardmarket['avg1'] ?? null,
                'average_7d' => $cardmarket['avg7'] ?? null,
                'average_30d' => $cardmarket['avg30'] ?? null,

                'average_holo' => $cardmarket['avg-holo'] ?? null,
                'low_holo' => $cardmarket['low-holo'] ?? null,
                'trend_holo' => $cardmarket['trend-holo'] ?? null,

                'average_1d_holo' => $cardmarket['avg1-holo'] ?? null,
                'average_7d_holo' => $cardmarket['avg7-holo'] ?? null,
                'average_30d_holo' => $cardmarket['avg30-holo'] ?? null,
            ];
        }

        return empty($result) ? null : $result;
    }

    /**
     * Map a TCGplayer pricing variant.
     */
    private function mapTcgPlayerVariant(?array $variant): ?array
    {
        if (!$variant) {
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

    public function mapBriefMany(array $cards): array
    {
        return array_map(
            fn (array $card) => $this->mapBrief($card),
            $cards
        );
    }
}