<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class ResettableStockController extends Controller
{
    /**
     * GET /stock/reseteables
     *
     * Stock de Productos Reseteables: cuentas elegibles para reset manual.
     *   - Todos los slots ocupados (sin cupos libres)
     *   - >= Account::RESET_ELIGIBLE_MONTHS meses desde la última asignación activa
     *
     * El reset en sí se dispara contra AccountController@reset (ruta accounts.reset).
     */
    public function index(Request $request)
    {
        $resettable = Account::query()
            ->resettableCandidates()                 // pre-filtro SQL (estado + ventana temporal)
            ->with(['game.products', 'assignments', 'keys'])
            ->when($request->filled('game_id'),  fn ($q) => $q->where('game_id', $request->game_id))
            ->when($request->filled('platform'), fn ($q) => $q->where('platform', $request->platform))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($q) use ($s) {
                    $q->where('email', 'like', "%$s%")
                        ->orWhereHas('game', function ($q) use ($s) {
                            $q->where('canonical_name', 'like', "%$s%")
                                ->orWhereHas('products', fn ($q) => $q->where('name', 'like', "%$s%"));
                        });
                });
            })
            ->get()
            // El chequeo "todos los slots ocupados" depende de capacity()/freeSlots(),
            // que son lógica PHP (no columnas) → se filtra acá, no en SQL.
            ->filter(fn (Account $a) => $a->isResettableStock())
            // Prioridad de rotación: mayor antigüedad desde el último reset (o desde la
            // compra si nunca se reseteó), en orden DESCENDENTE de antigüedad.
            // Las cuentas sin referencia (-1) quedan al final.
            ->sortByDesc(fn (Account $a) => $a->stockRotationAgeInDays() ?? -1)
            ->values();

        // Las stats reflejan el TOTAL (toda la colección), no solo la página actual.
        $stats = [
            'accounts' => $resettable->count(),
            'games'    => $resettable->pluck('game_id')->unique()->count(),
        ];

        // Paginación manual de 50: el filtro/orden es PHP, así que no se puede usar
        // ->paginate() de Eloquent. Armamos un LengthAwarePaginator sobre la colección
        // ya filtrada para conservar ->links() y mantener los filtros entre páginas.
        $perPage = 50;
        $page    = Paginator::resolveCurrentPage('page');

        $accounts = new LengthAwarePaginator(
            $resettable->forPage($page, $perPage)->values(),
            $resettable->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
        $accounts->withQueryString();   // conserva ?platform=... al cambiar de página

        // Opciones de filtro: las saco del universo completo para que el filtro sea usable.
        $platforms = Account::query()->select('platform')->distinct()->orderBy('platform')->pluck('platform');

        return view('stock.resettable', compact('accounts', 'stats', 'platforms'));
    }
}