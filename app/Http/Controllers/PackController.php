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

        return response()->json([
            'success' => true,
            'data'    => array_values($catalog),   // ← todo, sin cortar
        ]);
    }
    
}    