<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RerouteAccounts extends Command
{
    protected $signature = 'accounts:reroute
                            {--path=storage/app/import : Carpeta con accounts.json}
                            {--dry-run : Solo muestra qué cambiaría, no escribe}';

    protected $description = 'Reapunta game_id de cuentas existentes según el accounts.json regenerado. Por email. No crea ni borra cuentas.';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $file = base_path($this->option('path')) . '/accounts.json';
        if (! file_exists($file)) {
            $this->error("No encontré $file");
            return self::FAILURE;
        }

        // $accounts = json_decode(file_get_contents($file), true);
        // $slugToId = Game::pluck('id', 'slug');   // slug → id ACTUAL de la base

        // $stats = ['movidas' => 0, 'sin_match_db' => 0, 'sin_slug' => 0, 'igual' => 0, 'dup_email' => 0];

        // DB::beginTransaction();
        // foreach ($accounts as $a) {
        //     $email = $a['email'] ?? null;
        //     if (! $email) continue;

        //     $slug   = $a['game_slug'] ?? null;
        //     $gameId = $slug ? ($slugToId[$slug] ?? null) : null;

        //     // FRENO 1: si el JSON no trae destino concreto, NO tocamos la cuenta
        //     // (no pisamos una asignación manual con null).
        //     if (! $gameId) { $stats['sin_slug']++; continue; }

        //     // FRENO 2: solo INDEPENDIENTE, igual que el import original.
        //     // Las MADRE/HIJA y las creadas a mano no se tocan acá.
        //     $matches = Account::where('email', $email)
        //         ->where('account_type', 'INDEPENDIENTE')
        //         ->get();

        //     if ($matches->isEmpty()) { $stats['sin_match_db']++; continue; }
        //     if ($matches->count() > 1) { $stats['dup_email']++; }   // se reportan, igual se mueven todas

        //     foreach ($matches as $acc) {
        //         if ((int) $acc->game_id === (int) $gameId) { $stats['igual']++; continue; }

        //         $this->line(sprintf(
        //             '  %s · game_id %s → %d (%s)',
        //             $email, $acc->game_id ?? 'null', $gameId, $slug
        //         ));

        //         if (! $dry) {
        //             $acc->update(['game_id' => $gameId]);
        //         }
        //         $stats['movidas']++;
        //     }
        // }

        // $dry ? DB::rollBack() : DB::commit();

        $accounts = json_decode(file_get_contents($file), true);

        // Aplana: soporta el JSON plano (independientes) y el anidado (madres → hijas)
        $flat = [];
        foreach ($accounts as $a) {
            $hijas = $a['hijas'] ?? [];
            unset($a['hijas']);
            $flat[] = $a;
            foreach ($hijas as $h) {
                $flat[] = $h;
            }
        }

        $slugToId = Game::pluck('id', 'slug');
        $stats = ['movidas' => 0, 'sin_match_db' => 0, 'sin_slug' => 0, 'igual' => 0, 'dup_email' => 0];

        DB::beginTransaction();
        foreach ($flat as $a) {                          // ← ahora itera $flat, no $accounts
            $email = $a['email'] ?? null;
            if (! $email) continue;

            $slug   = $a['game_slug'] ?? null;
            $gameId = $slug ? ($slugToId[$slug] ?? null) : null;
            if (! $gameId) { $stats['sin_slug']++; continue; }

            // Para madres NO filtramos por INDEPENDIENTE: acá entran MADRE y HIJA
            $matches = Account::where('email', $email)->get();
            if ($matches->isEmpty()) { $stats['sin_match_db']++; continue; }
            if ($matches->count() > 1) { $stats['dup_email']++; }

            foreach ($matches as $acc) {
                if ((int) $acc->game_id === (int) $gameId) { $stats['igual']++; continue; }
                if (! $dry) $acc->update(['game_id' => $gameId]);
                $stats['movidas']++;
            }
        }
        $dry ? DB::rollBack() : DB::commit();
        $this->newLine();
        $this->info($dry ? '✓ DRY-RUN (no se escribió nada) Hija' : '✓ Re-ruteo aplicado');
        $this->table(['Métrica', 'Cuentas'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values());

        return self::SUCCESS;
    }
}