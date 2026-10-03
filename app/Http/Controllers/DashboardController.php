<?php

namespace App\Http\Controllers;

use App\Services\CollectionStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private CollectionStats $stats,
    ) {}

    /**
     * Show the collection summary for the authenticated user.
     *
     * The figures are aggregated in SQL rather than by reducing a loaded
     * collection, so the cost stays flat as the collection grows.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('dashboard', [
            'overall' => $this->stats->summarize(
                $request->user()->userCards()->getQuery()
            ),
        ]);
    }
}
