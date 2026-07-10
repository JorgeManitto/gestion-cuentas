<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AccountKey;
use App\Models\Game;
use App\Models\WooProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportNintendoInventory extends Command
{
    protected $signature = 'import:nintendo-inventory
                            {--path=storage/app/import-nintendo : Carpeta con games.json y accounts.json (export crudo de Supabase)}
                            {--check-only : Solo corre la verificación de juegos; NO importa nada}
                            {--fresh : Borra SOLO las cuentas Nintendo (SWITCH/SWITCH_2) antes de importar}';

    protected $description = 'Importa cuentas Nintendo desde el export de Supabase. Explota el array games[] en una cuenta por juego y verifica que cada juego referenciado exista.';

    /** Plataformas Nintendo tal como quedan guardadas en la BD (las usa --fresh). */
    private const NINTENDO_DB_PLATFORMS = ['SWITCH', 'SWITCH_2'];

    public function handle(): int
    {
        $path = base_path($this->option('path'));

        foreach (['games.json', 'accounts.json'] as $file) {
            if (! file_exists("$path/$file")) {
                $this->error("No encontré $path/$file");
                $this->line("Exportá desde Supabase las tablas 'games' y 'accounts' (filas Nintendo) como JSON y dejalas ahí.");
                return self::FAILURE;
            }
        }

        $games    = json_decode(file_get_contents("$path/games.json"), true) ?? [];
        $accounts = json_decode(file_get_contents("$path/accounts.json"), true) ?? [];

        // Mapa UUID (games.id de Supabase) → fila del juego. Es la fuente de verdad
        // contra la que verificamos las referencias del array accounts.games[].
        $gamesByUuid = [];
        foreach ($games as $g) {
            if (! empty($g['id'])) {
                $gamesByUuid[$g['id']] = $g;
            }
        }

        // ──────────────────────── 1) VERIFICACIÓN DE JUEGOS ────────────────────────
        // Antes de tocar nada: ¿cada UUID del array games[] de cada cuenta existe
        // realmente en games.json? Reportamos las referencias colgadas ("juego
        // guardado mal") y los juegos que existen pero se ven sospechosos.
        $dangling        = [];   // cuenta referencia un UUID que no está en games.json
        $suspicious      = [];   // el juego existe pero le falta id_woo / título / etc.
        $referencedUuids = [];

        foreach ($accounts as $a) {
            $gameUuids = $a['games'] ?? [];
            foreach ($gameUuids as $uuid) {
                $referencedUuids[$uuid] = true;

                if (! isset($gamesByUuid[$uuid])) {
                    $dangling[] = [
                        'account_email' => $a['email'] ?? '(sin email)',
                        'account_id'    => $a['id'] ?? '',
                        'game_uuid'     => $uuid,
                    ];
                    continue;
                }

                $g = $gamesByUuid[$uuid];
                $problem = match (true) {
                    empty($g['title'])                   => 'sin title',
                    empty($g['id_woo'])                  => 'sin id_woo (no matchea producto Woo)',
                    ($g['status'] ?? '') !== 'active'    => 'status = ' . ($g['status'] ?? 'null'),
                    default                              => null,
                };
                if ($problem) {
                    $suspicious[] = [
                        'game_uuid' => $uuid,
                        'title'     => $g['title'] ?? '(sin title)',
                        'problema'  => $problem,
                    ];
                }
            }
        }

        $this->info('── Verificación de juegos ──');
        $this->line('Juegos en games.json:            ' . count($gamesByUuid));
        $this->line('Cuentas en accounts.json:        ' . count($accounts));
        $this->line('UUIDs de juego referenciados:    ' . count($referencedUuids));
        $this->newLine();

        if ($dangling) {
            $this->error('⚠ Referencias ROTAS: ' . count($dangling) . ' (cuentas que apuntan a un juego que NO existe en games.json)');
            $this->table(['Email cuenta', 'UUID juego inexistente'],
                array_map(fn ($d) => [$d['account_email'], $d['game_uuid']], array_slice($dangling, 0, 50)));
            if (count($dangling) > 50) {
                $this->line('… y ' . (count($dangling) - 50) . ' más. Ver reporte completo en el archivo.');
            }
        } else {
            $this->info('✓ Todas las referencias de juego resuelven contra games.json.');
        }
        $this->newLine();

        if ($suspicious) {
            // Únicos por uuid para no repetir
            $uniqueSuspicious = collect($suspicious)->unique('game_uuid')->values()->all();
            $this->warn('Juegos sospechosos (existen pero se ven mal): ' . count($uniqueSuspicious));
            $this->table(['UUID', 'Título', 'Problema'],
                array_map(fn ($s) => [$s['game_uuid'], $s['title'], $s['problema']], array_slice($uniqueSuspicious, 0, 50)));
        }

        // Guardamos el reporte completo a disco para revisarlo con calma.
        $reportPath = "$path/verificacion-juegos.json";
        file_put_contents($reportPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'totales'      => [
                'juegos'                 => count($gamesByUuid),
                'cuentas'                => count($accounts),
                'uuids_referenciados'    => count($referencedUuids),
                'referencias_rotas'      => count($dangling),
                'juegos_sospechosos'     => count($suspicious),
            ],
            'referencias_rotas'  => $dangling,
            'juegos_sospechosos' => $suspicious,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->line("Reporte completo escrito en: $reportPath");
        $this->newLine();

        if ($this->option('check-only')) {
            $this->info('--check-only: no se importó nada.');
            return self::SUCCESS;
        }

        if ($dangling && ! $this->confirm('Hay referencias rotas. ¿Continuar igual? (esas se saltean)', false)) {
            $this->line('Abortado. Corregí games.json / accounts.json y volvé a correr.');
            return self::FAILURE;
        }

        // ──────────────────────── 2) --fresh (opcional) ────────────────────────
        if ($this->option('fresh')) {
            $this->warn('--fresh: borrando SOLO cuentas Nintendo (SWITCH/SWITCH_2) y sus llaves/asignaciones…');
            $ids = DB::table('accounts')->whereIn('platform', self::NINTENDO_DB_PLATFORMS)->pluck('id');
            DB::table('account_assignments')->whereIn('account_id', $ids)->delete();
            DB::table('account_keys')->whereIn('account_id', $ids)->delete();
            DB::table('accounts')->whereIn('platform', self::NINTENDO_DB_PLATFORMS)->delete();
        }

        // ──────────────────────── 3) GAMES + WOO PRODUCTS ────────────────────────
        // Cada juego de Supabase → un Game local + su WooProduct (id = id_woo).
        // Guardamos el mapa UUID → game_id local para linkear las cuentas.
        $this->info('Importando juegos y productos Woo…');
        $uuidToGameId = [];
        DB::transaction(function () use ($gamesByUuid, &$uuidToGameId) {
            foreach ($gamesByUuid as $uuid => $g) {
                $title = $g['title'] ?? null;
                if (! $title) {
                    continue;   // sin título no podemos crear un Game usable
                }
                $slug = ! empty($g['id_woo'])
                    ? 'woo-' . $g['id_woo']
                    : Str::slug($title);

                $game = Game::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'canonical_name'  => $title,
                        'normalized_name' => Str::of($title)->lower()->squish(),
                    ]
                );
                $uuidToGameId[$uuid] = $game->id;

                if (! empty($g['id_woo'])) {
                    WooProduct::updateOrCreate(
                        ['id' => $g['id_woo']],
                        [
                            'game_id'        => $game->id,
                            'name'           => $title,
                            'platform'       => $this->wooPlatform($g),
                            'image_url'      => $g['cover_image'] ?? null,
                            'category_raw'   => 'nintendo',
                            'last_synced_at' => now(),
                        ]
                    );
                }
            }
        });

        // ──────────────────────── 4) ACCOUNTS (una cuenta por juego) ────────────────────────
        $this->info('Importando cuentas Nintendo (una fila por juego)…');
        $bar = $this->output->createProgressBar(count($accounts));
        $stats = ['accounts' => 0, 'keys' => 0, 'skipped_dangling' => 0, 'skipped_no_game' => 0];

        DB::transaction(function () use ($accounts, $uuidToGameId, $bar, &$stats) {
            foreach ($accounts as $a) {
                $platform = $this->mapPlatform($a);
                $isDual   = (bool) ($a['is_dual_model'] ?? false);
                [$mailEmail, $mailPass] = $this->splitInternalMail($a['internal_email_password'] ?? null);

                $gameUuids = $a['games'] ?? [];
                foreach ($gameUuids as $uuid) {
                    $gameId = $uuidToGameId[$uuid] ?? null;
                    if (! $gameId) {
                        // Referencia rota o juego sin título: no la importamos.
                        $stats['skipped_dangling']++;
                        continue;
                    }

                    $account = Account::create([
                        'game_id'        => $gameId,
                        'platform'       => $platform,
                        'is_dual'        => $isDual,
                        'account_type'   => 'INDEPENDIENTE',
                        'region'         => $a['region'] ?? 'OTRO',
                        'email'          => $a['email'] ?? 'sin-email@desconocido.local',
                        'password'       => $a['password'] ?? '',
                        'mail_email'     => $mailEmail,
                        'mail_password'  => $mailPass,
                        'created_date'   => $this->date($a['created_at'] ?? null),
                        'purchased_date' => $this->date($a['purchase_date'] ?? null),
                        'reset_date'     => null,
                        'gamer_tag'      => $a['online_id'] ?? null,
                        'birth_date'     => $this->date($a['birth_date'] ?? null),
                        'status'         => 'active',
                        'notes'          => $a['notes'] ?? null,
                    ]);
                    $stats['accounts']++;

                    // NOTA (pendiente de confirmar): en Supabase las activation_keys son
                    // un POOL COMPARTIDO por la cuenta física (una sola cuenta con varios
                    // juegos). Al explotar en "una cuenta por juego", replicamos el pool
                    // completo en cada fila para que el vendedor tenga las llaves a mano.
                    // Esto NO reparte las llaves entre juegos; si preferís repartirlas,
                    // hay que definir la regla acá.
                    $keys = $a['activation_keys'] ?? [];
                    if ($keys) {
                        $keyRows = [];
                        foreach (array_values($keys) as $i => $val) {
                            $keyRows[] = [
                                'account_id' => $account->id,
                                'key_value'  => $val,
                                'position'   => $i + 1,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                        AccountKey::insert($keyRows);
                        $stats['keys'] += count($keyRows);
                    }
                }

                if (empty($gameUuids)) {
                    $stats['skipped_no_game']++;
                }

                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine(2);

        $this->info('✓ Importación Nintendo completa');
        $this->table(['Métrica', 'Valor'], [
            ['games (local)',            count($uuidToGameId)],
            ['accounts creadas',         $stats['accounts']],
            ['account_keys',             $stats['keys']],
            ['juegos salteados (rotos)', $stats['skipped_dangling']],
            ['cuentas sin juego',        $stats['skipped_no_game']],
        ]);

        return self::SUCCESS;
    }

    /** console_model "switch2" (+ is_dual_model) → plataforma local canónica. */
    private function mapPlatform(array $a): string
    {
        $model = strtolower(trim((string) ($a['console_model'] ?? '')));
        return match (true) {
            str_contains($model, 'switch2'), str_contains($model, 'switch 2') => 'SWITCH_2',
            default                                                           => 'SWITCH',
        };
    }

    /** Plataforma para el WooProduct del juego. */
    private function wooPlatform(array $g): string
    {
        $model = strtolower(trim((string) ($g['console_model'] ?? '')));
        return str_contains($model, 'switch2') || str_contains($model, 'switch 2')
            ? 'SWITCH_2'
            : 'SWITCH';
    }

    /**
     * internal_email_password viene como "correo@dominio / clave".
     * Devuelve [mail_email, mail_password].
     */
    private function splitInternalMail(?string $raw): array
    {
        if (! $raw) {
            return [null, null];
        }
        $parts = array_map('trim', explode('/', $raw, 2));
        return [$parts[0] ?: null, $parts[1] ?? null];
    }

    /** Normaliza timestamps de Supabase a fecha (Y-m-d) o null. */
    private function date(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
