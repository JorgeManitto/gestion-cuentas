<?php
namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BulkGameAssignmentController extends Controller
{
    /**
     * GET /accounts/bulk-assign
     * Lista TODAS las cuentas (incluye hijas) para asignar/reasignar game_id en lote.
     */
    public function index(Request $request)
    {
        $accounts = $this->baseQuery()
            ->with(['game.products'])
            ->when($request->filled('platform'), fn ($q) => $q->where('platform', $request->platform))
            ->when($request->filled('game'), fn ($q) => $this->applyGameFilter($q, $request->game))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(fn ($w) => $w->where('email', 'like', "%$s%")
                                       ->orWhere('gamer_tag', 'like', "%$s%"));
            })
            ->orderBy('platform')
            ->orderBy('email')
            ->paginate(50)
            ->withQueryString();

        $platforms = $this->baseQuery()
            ->select('platform')->distinct()->orderBy('platform')->pluck('platform');

        // Juegos que ya aparecen asignados en alguna cuenta → opciones del filtro
        $gameIds = $this->baseQuery()->whereNotNull('game_id')->distinct()->pluck('game_id');
        $games   = Game::whereIn('id', $gameIds)->orderBy('canonical_name')->get(['id', 'canonical_name']);

        $filteredTotal = $accounts->total();

        return view('accounts.bulk-assign', compact('accounts', 'platforms', 'games', 'filteredTotal'));
    }

    /**
     * POST /accounts/bulk-assign
     * Aplica game_id a las seleccionadas (o a TODAS las del filtro si select_all=1).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'game_id'         => ['required', 'exists:games,id'],
            'account_ids'     => ['array'],
            'account_ids.*'   => ['integer', 'exists:accounts,id'],
            'select_all'      => ['nullable', 'boolean'],
            // se reenvían los filtros para reconstruir el set cuando select_all=1
            'filter_search'   => ['nullable', 'string'],
            'filter_platform' => ['nullable', 'string'],
            'filter_game'     => ['nullable', 'string'],
        ]);

        $selectAll = $request->boolean('select_all');

        if (! $selectAll && empty($data['account_ids'] ?? [])) {
            return back()
                ->withErrors(['account_ids' => 'Seleccioná al menos una cuenta o marcá "todas las del filtro".'])
                ->withInput();
        }

        $query = $this->baseQuery();

        if ($selectAll) {
            $query
                ->when($data['filter_platform'] ?? null, fn ($q, $p) => $q->where('platform', $p))
                ->when($data['filter_game'] ?? null, fn ($q, $g) => $this->applyGameFilter($q, $g))
                ->when($data['filter_search'] ?? null, function ($q, $s) {
                    $q->where(fn ($w) => $w->where('email', 'like', "%$s%")
                                           ->orWhere('gamer_tag', 'like', "%$s%"));
                });
        } else {
            $query->whereIn('id', $data['account_ids']);
        }

        $game  = Game::findOrFail($data['game_id']);
        $count = DB::transaction(fn () => $query->update(['game_id' => $game->id]));

        return back()->with(
            'success',
            "Se asignaron {$count} cuenta(s) al juego «{$game->displayName()}»."
        );
    }

    /** Base: todas las cuentas (el soft-delete se respeta por el scope global). */
    private function baseQuery()
    {
        return Account::query();
    }

    /** Filtro de juego. Valor especial "none" = cuentas sin juego. */
    private function applyGameFilter($query, string $value)
    {
        return $value === 'none'
            ? $query->whereNull('game_id')
            : $query->where('game_id', $value);
    }
}