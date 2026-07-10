<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Game;
use App\Models\WooProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Repara la desincronización cuenta ↔ juego ↔ producto Woo.
 *
 * Fase 1 — Fusiona juegos duplicados (mismo canonical_name en varias filas de
 *          `games`). Sobrevive la fila con más productos; se le mueven cuentas y
 *          productos de las gemelas y se borran las gemelas.
 *
 * Fase 2 — Re-apunta las cuentas huérfanas (cuyo juego no tiene un producto para
 *          la plataforma de la cuenta) al juego que sí tiene ese producto,
 *          matcheando por nombre base normalizado + plataforma.
 *
 * Por defecto corre en DRY-RUN (no escribe nada). Usá --apply para persistir.
 */
class FixDuplicateGames extends Command
{
    protected $signature = 'games:dedupe
                            {--apply : Persiste los cambios. Sin este flag es dry-run (solo muestra).}';

    protected $description = 'Fusiona juegos duplicados y re-apunta las cuentas huérfanas al producto Woo correcto';

    private bool $apply = false;

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply');

        $this->line($this->apply
            ? '<fg=yellow>MODO APPLY: se van a persistir los cambios.</>'
            : '<fg=cyan>DRY-RUN: no se escribe nada. Usá --apply para persistir.</>');
        $this->newLine();

        if ($this->apply) {
            DB::transaction(function () {
                $this->mergeDuplicates();
                $this->repointOrphans();
            });
        } else {
            // En dry-run no abrimos transacción: solo reportamos.
            $this->mergeDuplicates();
            $this->repointOrphans();
        }

        $this->newLine();
        $this->info($this->apply ? '✓ Reparación aplicada.' : '✓ Dry-run completo (no se escribió nada).');

        return self::SUCCESS;
    }

    // ──────────────────────── FASE 1: FUSIÓN DE DUPLICADOS ────────────────────────

    private function mergeDuplicates(): void
    {
        $this->line('<fg=green;options=bold>FASE 1 — Juegos duplicados</>');

        $dupNames = Game::query()
            ->select('canonical_name')
            ->groupBy('canonical_name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('canonical_name');

        if ($dupNames->isEmpty()) {
            $this->line('  Sin duplicados. ✓');
            $this->newLine();
            return;
        }

        $rows = [];
        $mergedGroups = 0;
        $deletedGames = 0;
        $movedProducts = 0;
        $movedAccounts = 0;

        foreach ($dupNames as $name) {
            $games = Game::where('canonical_name', $name)
                ->withCount(['products'])
                ->get();

            // Sobrevive: más productos → (empate) más cuentas → (empate) id más bajo.
            $survivor = $games->sort(function ($a, $b) {
                return [$b->products_count, $this->accountCount($b), $a->id]
                    <=> [$a->products_count, $this->accountCount($a), $b->id];
            })->first();

            $losers = $games->where('id', '!=', $survivor->id);

            foreach ($losers as $loser) {
                $prodCount = WooProduct::where('game_id', $loser->id)->count();
                $accCount  = Account::withTrashed()->where('game_id', $loser->id)->count();

                $rows[] = [
                    Str::limit($name, 55),
                    "#{$loser->id} → #{$survivor->id}",
                    $prodCount,
                    $accCount,
                ];

                if ($this->apply) {
                    WooProduct::where('game_id', $loser->id)->update(['game_id' => $survivor->id]);
                    Account::withTrashed()->where('game_id', $loser->id)->update(['game_id' => $survivor->id]);
                    $loser->delete();
                }

                $movedProducts += $prodCount;
                $movedAccounts += $accCount;
                $deletedGames++;
            }

            $mergedGroups++;
        }

        $this->table(
            ['Juego', 'Fusión (loser → survivor)', 'Prod. mov.', 'Cuentas mov.'],
            $rows
        );

        $this->line("  Grupos fusionados: <fg=yellow>{$mergedGroups}</>  ·  "
            . "Juegos borrados: <fg=yellow>{$deletedGames}</>  ·  "
            . "Productos movidos: <fg=yellow>{$movedProducts}</>  ·  "
            . "Cuentas movidas: <fg=yellow>{$movedAccounts}</>");
        $this->newLine();
    }

    private function accountCount(Game $game): int
    {
        return Account::withTrashed()->where('game_id', $game->id)->count();
    }

    // ──────────────────────── FASE 2: RE-APUNTAR HUÉRFANAS ────────────────────────

    private function repointOrphans(): void
    {
        $this->line('<fg=green;options=bold>FASE 2 — Cuentas huérfanas</>');

        // Índice (nombre base normalizado, plataforma) → game_id que tiene ese producto.
        // Se arma con el estado ACTUAL de la DB (en --apply, ya con la fase 1 aplicada
        // dentro de la misma transacción).
        $index = [];
        WooProduct::query()->with('game')->chunk(500, function ($chunk) use (&$index) {
            foreach ($chunk as $p) {
                if (! $p->game_id) {
                    continue;
                }
                $plat = WooProduct::normalizePlatform($p->platform);
                if (! $plat) {
                    continue;
                }
                $base = $this->baseKey($p->name);
                // Determinismo: si dos juegos matchean, nos quedamos con el id más bajo.
                if (! isset($index[$base][$plat]) || $p->game_id < $index[$base][$plat]) {
                    $index[$base][$plat] = $p->game_id;
                }
            }
        });

        $repointed = [];
        $manual = [];

        Account::query()
            ->whereNotNull('game_id')
            ->with('game.products')
            ->chunk(500, function ($chunk) use ($index, &$repointed, &$manual) {
                foreach ($chunk as $account) {
                    // ¿Ya resuelve producto? Entonces no está huérfana.
                    if ($account->coverProduct() !== null) {
                        continue;
                    }

                    $base   = $this->baseKey($account->game?->canonical_name);
                    $plat   = $account->platform;
                    $target = $index[$base][$plat] ?? null;

                    if ($target && $target !== $account->game_id) {
                        $repointed[] = [
                            $account->id,
                            $plat,
                            "#{$account->game_id} → #{$target}",
                            Str::limit($account->game?->canonical_name ?? '—', 50),
                        ];

                        if ($this->apply) {
                            // update directo para no disparar eventos/timestamps de más.
                            Account::whereKey($account->id)->update(['game_id' => $target]);
                        }
                    } else {
                        $manual[] = [
                            $account->id,
                            $plat,
                            $account->game_id,
                            Str::limit($account->game?->canonical_name ?? '—', 55),
                        ];
                    }
                }
            });

        if ($repointed) {
            $this->line('  <fg=green>Re-apuntadas automáticamente:</>');
            $this->table(['Cuenta', 'Plat.', 'game_id', 'Juego'], $repointed);
        }

        if ($manual) {
            $this->line('  <fg=red>Sin match automático (revisar a mano):</>');
            $this->table(['Cuenta', 'Plat.', 'game_id', 'Juego (canonical)'], $manual);
        }

        $this->line('  Re-apuntadas: <fg=yellow>' . count($repointed) . '</>  ·  '
            . 'Manuales: <fg=yellow>' . count($manual) . '</>');
    }

    /**
     * Clave de matcheo: nombre en minúsculas, sin acentos, sin tokens de plataforma
     * ni ruido de edición, solo alfanuméricos. Local a este comando (no toca
     * Game::stripPlatform) — se usa únicamente para emparejar cuenta ↔ producto.
     */
    private function baseKey(?string $name): string
    {
        $s = Str::ascii((string) $name);
        $s = mb_strtolower($s);
        $s = preg_replace('/[™®©]/u', ' ', $s);

        // Tokens de plataforma (incluye variantes/typos vistos en prod).
        $s = preg_replace(
            '/\b(ps5|ps4|playstation\s*[45]?|xbox\s*(one|series)?(\s*x)?(\s*[\/|]?\s*s)?|series\s*x[\s\/|]*s|nintendo\s+sw[ia]tch(\s*2)?|sw[ia]tch\s*2?|steam|pc)\b/u',
            ' ',
            $s
        );

        // Ruido de edición / catálogo que aparece pegado al final.
        $s = preg_replace('/\b(pre\s*orden|pre\s*order|ingles|exclusivo)\b/u', ' ', $s);

        // Solo alfanuméricos + colapsar espacios.
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
