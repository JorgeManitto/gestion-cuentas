<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /**
     * GET /games/picker
     *
     * Devuelve una página de juegos con su cover, en JSON.
     * Usado por el modal "seleccionar juego" del form de cuentas.
     */
    public function picker(Request $request): JsonResponse
    {
        $perPage = 16;   // 4×4

        $games = Game::query()
            ->with(['products' => fn ($q) => $q->whereNotNull('image_url')->limit(1)])
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($q) use ($s) {
                    $q->where('canonical_name', 'like', "%$s%")
                      ->orWhere('normalized_name', 'like', "%$s%");
                });
            })
            ->orderBy('canonical_name')
            ->paginate($perPage);

        return response()->json([
            'data' => $games->map(fn ($g) => [
                'id'             => $g->id,
                'canonical_name' => $g->canonical_name,
                'cover_image_url' => $g->cover_image_url,
            ]),
            'meta' => [
                'current_page' => $games->currentPage(),
                'last_page'    => $games->lastPage(),
                'total'        => $games->total(),
            ],
        ]);
    }
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));

        $games = Game::query()
            ->with(['products' => fn ($q) => $q->orderBy('name')])
            ->withCount(['products', 'accounts'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('canonical_name', 'like', "%{$search}%")
                       ->orWhere('normalized_name', 'like', "%{$search}%")
                       ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('canonical_name')
            ->paginate(24)
            ->withQueryString();

        return view('games.index', compact('games', 'search'));
    }

    public function show(Game $game)
    {
        $game->load([
            'products' => fn ($q) => $q->orderBy('platform')->orderBy('name'),
            'accounts',
        ]);

        return view('games.show', compact('game'));
    }
}
