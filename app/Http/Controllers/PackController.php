<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\MatchmakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class PackController extends Controller
{
    public function __construct(private MatchmakingService $matchmaking) {}

    public function packCandidates(Request $request): JsonResponse
    {
        $perPage = 24;
        $page    = max(1, (int) $request->input('page', 1));

        $accounts = $this->matchmaking
            ->secondaryCandidatesQuery($request->input('search'))
            ->with(['game.products', 'assignments', 'secondaryAssignments'])
            ->get()
            ->filter(fn (Account $acc) => $acc->isSecondaryStockEligible());

        $catalog = [];

        foreach ($accounts as $acc) {
            foreach ($acc->secondaryPlatforms() as $platform) {
                if ($acc->secondaryFreeSlotsFor($platform) < 1) {
                    continue;
                }

                $product = $acc->game?->productForPlatform($platform)
                    ?? $acc->coverProduct();

                if (! $product) {
                    continue;
                }

                $catalog[$product->id] ??= [
                    'product_id' => $product->id,
                    'game'       => $acc->game?->displayName() ?: $product->name,
                    'platform'   => $platform,
                    'image_url'  => $product->image_url,
                ];
            }
        }

        // Dedupe listo → paginamos el array final, que es lo que realmente mostramos.
        $all   = array_values($catalog);
        $total = count($all);
        $items = array_slice($all, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'current_page' => $page,
                'last_page'    => (int) max(1, ceil($total / $perPage)),
                'total'        => $total,
            ],
        ]);
    }
}    