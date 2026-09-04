<?php

namespace App\Http\Controllers;

use App\Services\TcgDexService;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private TcgDexService $TcgDexService
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('welcome', [
            'featuredCards' => $this->TcgDexService->getFeaturedCards(),
        ]);
    }
}