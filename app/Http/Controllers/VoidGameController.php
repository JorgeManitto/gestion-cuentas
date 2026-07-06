<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Game;
use Illuminate\Http\Request;

class VoidGameController extends Controller
{
    /**
     * GET /juegos/sin-producto
     *
     * Lista los juegos que tienen canonical_name pero NINGÚN WooProduct
     * asociado: es decir, juegos que nunca se encontraron en el catálogo
     * de WooCommerce.
     */
    public function withoutProduct(Request $request)
    {
        $games = Game::query()
            ->whereDoesntHave('products')
            ->select('games.*')
            // Contamos cuentas con una subconsulta, sin depender de que el
            // modelo Game tenga definida una relación accounts().
            ->selectSub(
                Account::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('accounts.game_id', 'games.id'),
                'accounts_count'
            )
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($q) use ($s) {
                    $q->where('canonical_name', 'like', "%$s%")
                      ->orWhere('normalized_name', 'like', "%$s%")
                      ->orWhere('slug', 'like', "%$s%");
                });
            })
            ->orderBy('canonical_name')
            ->paginate(50)
            ->withQueryString();

        $total = Game::whereDoesntHave('products')->count();

        return view('games.without_product', compact('games', 'total'));
    }
}
