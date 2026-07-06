<?php

namespace App\Http\Controllers;

use App\Models\WooProduct;
use Illuminate\Http\Request;

class WooProductPickerController extends Controller
{
    public function __invoke(Request $request)
    {
        $search = trim($request->query('search', ''));

        $query = WooProduct::query()
            ->with('game')
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('platform', 'like', "%{$search}%")
                  ->orWhereHas('game', fn ($g) =>
                      $g->where('canonical_name', 'like', "%{$search}%"));
            });
        }

        $products = $query->paginate(24)->withQueryString();

        return response()->json([
            'data' => $products->getCollection()->map(fn (WooProduct $p) => [
                'id'         => $p->id,
                'game_id'    => $p->game_id,
                'name'       => $p->name,
                'platform'   => $this->normalizePlatform($p->platform), // enum del form o null
                'image_url'  => $p->image_url,
                'game_name'  => $p->game?->canonical_name,
            ])->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'total'        => $products->total(),
            ],
        ]);
    }

    /**
     * Mapea el platform crudo de WooCommerce al enum que usa el form de cuentas.
     * Devuelve null si no lo reconoce (el operario lo elige a mano).
     * Ajustá los str_contains a como vengan tus categorías reales.
     */
    protected function normalizePlatform(?string $raw): ?string
    {
        if (! $raw) return null;
        $p = mb_strtoupper(trim($raw));

        return match (true) {
            str_contains($p, 'SWITCH 2'), str_contains($p, 'SWITCH2')       => 'SWITCH_2',
            str_contains($p, 'SWITCH')                                       => 'SWITCH',
            str_contains($p, 'PS5'), str_contains($p, 'PLAYSTATION 5')       => 'PS5',
            str_contains($p, 'PS4'), str_contains($p, 'PLAYSTATION 4')       => 'PS4',
            str_contains($p, 'SERIES')                                       => 'XBOX_SERIES',
            str_contains($p, 'XBOX ONE'), $p === 'XBOX'                      => 'XBOX_ONE',
            str_contains($p, 'STEAM'), str_contains($p, 'PC')                => 'STEAM',
            default                                                          => null,
        };
    }
}