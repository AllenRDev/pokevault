<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TcgDexService
{
    protected string $baseUrl;

    public function __construct(
        private TcgDexCardMapper $cardMapper
    ) {
        $this->baseUrl = config('services.tcgdex.base_url');
    }

    public function searchCards(string $query): array
{
    return Cache::remember(
        'tcgdex.search.' . md5(strtolower(trim($query))),
        now()->addMinutes(10),
        function () use ($query) {
            $response = Http::baseUrl($this->baseUrl)
                ->retry(3, 200)
                ->get('/cards', [
                    'name' => $query,
                ])
                ->throw()
                ->json();

            return [
                'data' => $this->cardMapper->mapBriefMany($response),
                'count' => count($response),
            ];
        }
    );
}

    public function getCard(string $id): array
    {
        return Cache::remember(
            'tcgdex.card.' . $id,
            now()->addHours(24),
            function () use ($id) {
                $response = Http::baseUrl($this->baseUrl)
                    ->retry(3, 200)
                    ->get("/cards/{$id}")
                    ->throw()
                    ->json();

                return $this->cardMapper->map($response);
            }
        );
    }

    public function getFeaturedCards(): array
{
    return Cache::remember(
        'tcgdex.featured',
        now()->addHours(6),
        function () {
            $response = Http::baseUrl($this->baseUrl)
                ->retry(3, 200)
                ->get('/cards', [
                    'name' => 'Mawile',
                ])
                ->throw()
                ->json();

            return $this->cardMapper->mapBriefMany(
                array_slice($response, 0, 4)
            );
        }
    );
}
}