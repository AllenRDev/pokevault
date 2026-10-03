<?php

namespace App\Http\Controllers;

use App\Exceptions\CardNotFoundException;
use App\Services\TcgDexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    public function __construct(
        private TcgDexService $tcgDexService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $cards = $this->tcgDexService->searchCards(
            $validated['q']
        );

        return response()->json($cards);
    }

    /**
     * Match text read off a card against the catalogue.
     *
     * A scan produces a name and, when the corner of the card is legible,
     * the `number/total` pair printed on the front. The pair is what makes
     * the match specific, so the response reports how much the catalogue
     * could narrow things down and the client never has to guess.
     */
    public function match(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'number' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'total' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $result = $this->tcgDexService->matchCards(
            $validated['name'],
            $validated['number'] ?? null,
            $validated['total'] ?? null,
        );

        $candidates = $result['candidates'];
        $count = count($candidates);

        $confidence = match (true) {
            $count === 0 => 'none',
            $result['resolved'] && $count === 1 => 'exact',
            default => 'ambiguous',
        };

        return response()->json([
            'confidence' => $confidence,
            'candidates' => $candidates,
            'query' => [
                'name' => $validated['name'],
                // A query string is never typed, so the integers are cast.
                'number' => isset($validated['number'])
                    ? (int) $validated['number']
                    : null,
                'total' => isset($validated['total'])
                    ? (int) $validated['total']
                    : null,
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(
            $this->cardOrFail($id)
        );
    }

    /**
     * Render the card detail page.
     */
    public function page(string $id): Response
    {
        return Inertia::render('cards/show', [
            'card' => $this->cardOrFail($id),
            'isAuthenticated' => auth()->check(),
        ]);
    }

    /**
     * Fetch a card, turning a missing card into a 404.
     *
     * @return array<string, mixed>
     */
    private function cardOrFail(string $id): array
    {
        try {
            return $this->tcgDexService->getCard($id);
        } catch (CardNotFoundException) {
            abort(404, 'Card not found.');
        }
    }
}
