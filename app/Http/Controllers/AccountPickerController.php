<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountPickerController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'type'    => 'nullable|in:MADRE,HIJA,INDEPENDIENTE',
            'search'  => 'nullable|string|max:255',
            'exclude' => 'nullable|integer', // id a excluir (p.ej. la propia cuenta al editar)
            'page'    => 'nullable|integer|min:1',
        ]);

        $accounts = Account::query()
            ->when($data['type'] ?? null, fn ($q, $type) => $q->where('account_type', $type))
            ->when($data['exclude'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))
            ->when($data['search'] ?? null, function ($q, $term) {
                $like = '%' . mb_strtolower($term) . '%';
                $q->where(fn ($w) => $w
                    ->whereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(region) LIKE ?', [$like]));
            })
            ->orderBy('email')
            ->paginate(20, ['id', 'email', 'account_type', 'region', 'parent_account_id'])
            ->withQueryString();

        return response()->json([
            'data' => $accounts->getCollection()->map(fn ($a) => [
                'id'           => $a->id,
                'email'        => $a->email,
                'account_type' => $a->account_type,
                'region'       => $a->region,
                'has_parent'   => ! is_null($a->parent_account_id),
            ])->values(),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'last_page'    => $accounts->lastPage(),
                'total'        => $accounts->total(),
            ],
        ]);
    }
}