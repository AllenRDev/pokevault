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

        $setId = $card['set']['id'] ?? null;

        return [
            'external_id' => $card['id'],
            'name' => $card['name'],
            'number' => $card['localId'] ?? '',
            'rarity' => $card['rarity'] ?? null,

            'image_small' => $image
                ? "{$image}/low.webp"
                : null,

            'image_large' => $image
                ? "{$image}/high.webp"
                : null,

            'prices' => $this->mapPricing(
                $card['pricing'] ?? null
            ),

            'set' => [
    'external_id' => $card['set']['id'] ?? '',
    'name' => $card['set']['name'] ?? '',
    'series' => $card['set']['serie']['name'] ?? null,
    'printed_total' => $card['set']['cardCount']['official'] ?? null,
    'release_date' => $card['set']['releaseDate'] ?? null,

    'logo_url' => $this->mapSetImage(
        $card['set']['logo'] ?? null
    ),

    'symbol_url' => $this->mapSetImage(
        $card['set']['symbol'] ?? null
    ),
],
        ];
    }
    
private function mapSetImage(?string $image): ?string
{
    if (!$image) {
        return null;
    }

    return "{$image}.webp";
}

    private function mapPricing(?array $pricing): ?array
    {
        if (!$pricing) {
            return null;
        }

        return [
            'tcgplayer' => [
                'updated' => $pricing['tcgplayer']['updated'] ?? null,
                'unit' => $pricing['tcgplayer']['unit'] ?? 'USD',
                'normal' => $this->mapTcgPlayerVariant(
                    $pricing['tcgplayer']['normal'] ?? null
                ),
                'holofoil' => $this->mapTcgPlayerVariant(
                    $pricing['tcgplayer']['holofoil'] ?? null
                ),
                'reverse_holofoil' => $this->mapTcgPlayerVariant(
                    $pricing['tcgplayer']['reverse-holofoil'] ?? null
                ),
                'first_edition' => $this->mapTcgPlayerVariant(
                    $pricing['tcgplayer']['1st-edition'] ?? null
                ),
            ],

            'cardmarket' => [
                'updated' => $pricing['cardmarket']['updated'] ?? null,
                'unit' => $pricing['cardmarket']['unit'] ?? 'EUR',
                'avg' => $pricing['cardmarket']['avg'] ?? null,
                'low' => $pricing['cardmarket']['low'] ?? null,
                'trend' => $pricing['cardmarket']['trend'] ?? null,
                'avg1' => $pricing['cardmarket']['avg1'] ?? null,
                'avg7' => $pricing['cardmarket']['avg7'] ?? null,
                'avg30' => $pricing['cardmarket']['avg30'] ?? null,
            ],
        ];
    }

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

    public function getMarketPrice(array $cardData): ?float
{
    return $cardData['prices']['tcgplayer']['normal']['market']
        ?? $cardData['prices']['tcgplayer']['holofoil']['market']
        ?? $cardData['prices']['tcgplayer']['reverse_holofoil']['market']
        ?? $cardData['prices']['tcgplayer']['first_edition']['market']
        ?? null;
}

    /**
     * Map multiple lightweight cards.
     */
    public function mapBriefMany(array $cards): array
    {
        return array_map(
            fn (array $card) => $this->mapBrief($card),
            $cards
        );
    }
}