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
}
