<?php

namespace App\Http\Controllers;

use App\Services\TcgDexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    public function __construct(
        private TcgDexService $tcgDexService,
    ) {
    }

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

    public function show(string $id): JsonResponse
    {
        $card = $this->tcgDexService->getCard($id);

        return response()->json($card);
    }

    public function page(string $id): Response
{
    return Inertia::render('cards/show', [
        'card' => $this->tcgDexService->getCard($id),
    ]);
}
}