<?php

namespace App\Services;

use App\Exceptions\CardNotFoundException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;
use UnexpectedValueException;

class TcgDexService
{
    /**
     * How many search results are inspected when the set total has to be
     * resolved. Each candidate costs a detail request, so this is capped
     * rather than resolved exhaustively.
     */
    public const MATCH_CANDIDATE_LIMIT = 12;

    protected string $baseUrl;

    public function __construct(
        private TcgDexCardMapper $cardMapper
    ) {
        $this->baseUrl = config('services.tcgdex.base_url');
    }

    /**
     * @return array{data: list<array<string, mixed>>, count: int}
     */
    public function searchCards(string $query): array
    {
        return Cache::remember(
            'tcgdex.search.'.md5(strtolower(trim($query))),
            now()->addMinutes(10),
            function () use ($query) {
                $response = Http::baseUrl($this->baseUrl)
                    ->retry(3, 200)
                    ->get('/cards', [
                        'name' => $query,
                    ])
                    ->throw()
                    ->json();

                $cards = $this->toCardList($response);

                return [
                    'data' => $this->cardMapper->mapBriefMany($cards),
                    'count' => count($cards),
                ];
            }
        );
    }

    /**
     * Find the cards a scanned card could be.
     *
     * A card front prints its number as `number/total`, but the set symbol
     * printed beside that number is a graphic rather than text, so the
     * number on its own is ambiguous: card 45 exists in every set. The
     * total is plain digits, and each set's printed size is known, so
     * comparing the two identifies the printing without reading artwork.
     *
     * @return array{candidates: list<array<string, mixed>>, resolved: bool}
     */
    public function matchCards(
        string $name,
        ?int $number = null,
        ?int $total = null
    ): array {
        $briefs = $this->searchCards($name)['data'];

        $matches = array_values(array_filter(
            $briefs,
            fn (array $card): bool => $number === null
                || $this->localNumber($card['number'] ?? null) === $number
        ));

        $matches = array_slice($matches, 0, self::MATCH_CANDIDATE_LIMIT);

        /*
         * Without a total there is nothing to disambiguate against, and the
         * brief records carry no set, so they are returned as they are.
         */
        if ($matches === [] || $total === null) {
            return ['candidates' => $matches, 'resolved' => false];
        }

        $resolved = [];

        foreach ($matches as $match) {
            try {
                $card = $this->getCard((string) $match['external_id']);
            } catch (CardNotFoundException) {
                continue;
            }

            if ($this->printedTotal($card) === $total) {
                $resolved[] = $card;
            }
        }

        return $resolved !== []
            ? ['candidates' => $resolved, 'resolved' => true]
            : ['candidates' => $matches, 'resolved' => false];
    }

    /**
     * Read the card number out of a TCGdex `localId`.
     *
     * Ids can carry a variant suffix such as `45a`, so the leading run of
     * digits is taken rather than casting the whole value.
     */
    private function localNumber(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        if (preg_match('/\d+/', (string) $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }

    /**
     * The printed size of the set a card belongs to.
     *
     * @param  array<string, mixed>  $card
     */
    private function printedTotal(array $card): ?int
    {
        $set = $card['set'] ?? null;

        if (! is_array($set)) {
            return null;
        }

        $total = $set['printed_total'] ?? null;

        return is_int($total) ? $total : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCard(string $id, bool $fresh = false): array
    {
        $key = 'tcgdex.card.'.$id;

        if ($fresh) {
            $card = $this->fetchCard($id);

            Cache::put($key, $card, now()->addHours(24));

            return $card;
        }

        return Cache::remember(
            $key,
            now()->addHours(24),
            fn (): array => $this->fetchCard($id)
        );
    }

    /**
     * Request a single card, bypassing the read cache.
     *
     * @return array<string, mixed>
     *
     * @throws CardNotFoundException
     */
    private function fetchCard(string $id): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                /*
                 * A card that does not exist will not exist on a retry, so
                 * only genuine upstream failures are retried.
                 */
                ->retry(3, 200, when: function (Throwable $exception): bool {
                    return ! $exception instanceof RequestException
                        || $exception->response->status() !== 404;
                })
                ->get("/cards/{$id}")
                ->throw();
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                throw CardNotFoundException::forId($id);
            }

            throw $exception;
        }

        return $this->cardMapper->map(
            $this->toCard($response->json())
        );
    }

    /**
     * Cards for the landing page's featured section.
     *
     * A single search is used, so the results are several printings of the
     * same Pokémon rather than four different ones.
     *
     * @return list<array<string, mixed>>
     */
    public function getFeaturedCards(): array
    {
        return Cache::remember(
            'tcgdex.featured',
            now()->addHours(6),
            function () {
                $response = Http::baseUrl($this->baseUrl)
                    ->retry(3, 200)
                    ->get('/cards', [
                        'name' => config('services.tcgdex.featured_card'),
                    ])
                    ->throw()
                    ->json();

                return $this->cardMapper->mapBriefMany(
                    array_slice($this->toCardList($response), 0, 4)
                );
            }
        );
    }

    /**
     * Narrow a decoded JSON payload to a list of card objects.
     *
     * anything that is not an object is dropped so the mapper is only ever
     * handed arrays, and a non-list payload degrades to an empty result
     * rather than raising a type error.
     *
     * @return list<array<string, mixed>>
     */
    private function toCardList(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $cards = [];

        foreach ($payload as $item) {
            if (is_array($item)) {
                $cards[] = $this->toStringKeyed($item);
            }
        }

        return $cards;
    }

    /**
     * Narrow a decoded JSON payload to a single card object.
     *
     * @return array<string, mixed>
     */
    private function toCard(mixed $payload): array
    {
        if (! is_array($payload)) {
            throw new UnexpectedValueException(
                'TCGdex returned an unexpected payload for a card.'
            );
        }

        return $this->toStringKeyed($payload);
    }

    /**
     * Discard non-string keys so the result satisfies the shape the
     * mapper is annotated with.
     *
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    private function toStringKeyed(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
