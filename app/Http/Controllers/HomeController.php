<?php

namespace App\Http\Controllers;

use App\Services\TcgDexService;
use Illuminate\Http\Client\HttpClientException;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private TcgDexService $tcgDexService
    ) {}

    public function index(): Response
    {
        return Inertia::render('welcome', [
            'featuredCards' => $this->featuredCards(),
        ]);
    }

    /**
     * Featured cards are decoration, so an upstream outage must not take the
     * landing page down with it.
     *
     * @return list<array<string, mixed>>
     */
    private function featuredCards(): array
    {
        try {
            return $this->tcgDexService->getFeaturedCards();
        } catch (HttpClientException $exception) {
            report($exception);

            return [];
        }
    }
}
