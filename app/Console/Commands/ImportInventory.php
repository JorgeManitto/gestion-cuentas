<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AccountAssignment;
use App\Models\AccountKey;
use App\Models\Game;
use App\Models\WooProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportInventory extends Command
{
    protected $signature = 'import:inventory
                            {--path=storage/app/import : Carpeta con games.json, woo_products.json, accounts.json}
                            {--fresh : Vaciar las tablas antes de importar}';

    protected $description = 'Importa games, woo_products y accounts desde los JSON generados por el script Python';

    public function handle(): int
    {
        $path = base_path($this->option('path'));

        foreach (['games.json', 'woo_products.json', 'accounts.json'] as $file) {
            if (! file_exists("$path/$file")) {
                $this->error("No encontré $path/$file");
                return self::FAILURE;
            }
        }

        if ($this->option('fresh')) {
            $this->warn('--fresh: vaciando tablas…');
            Schema::disableForeignKeyConstraints();
            DB::table('account_assignments')->truncate();
            DB::table('account_keys')->truncate();
            DB::table('accounts')->truncate();
            DB::table('woo_products')->truncate();
            DB::table('games')->truncate();
            Schema::enableForeignKeyConstraints();
        }

        // 1) GAMES
        $games = json_decode(file_get_contents("$path/games.json"), true);
        $this->info("Importando " . count($games) . " juegos…");
        $bar = $this->output->createProgressBar(count($games));
        $slugToId = [];   // slug → game_id, lo usamos en los siguientes pasos
        DB::transaction(function () use ($games, &$slugToId, $bar) {
            foreach (array_chunk($games, 200) as $chunk) {
                foreach ($chunk as $g) {
                    $game = Game::updateOrCreate(
                        ['slug' => $g['slug']],
                        [
                            'canonical_name'  => $g['canonical_name'],
                            'normalized_name' => $g['normalized_name'],
                        ]
                    );
                    $slugToId[$g['slug']] = $game->id;
                    $bar->advance();
                }
            }
        });
        $bar->finish();
        $this->newLine();

        // 2) WOO PRODUCTS
        $products = json_decode(file_get_contents("$path/woo_products.json"), true);
        $this->info("Importando " . count($products) . " productos del Woo…");
        $bar = $this->output->createProgressBar(count($products));
        DB::transaction(function () use ($products, $slugToId, $bar) {
            foreach (array_chunk($products, 500) as $chunk) {
                $rows = [];
                foreach ($chunk as $p) {
                    $rows[] = [
                        'id'             => $p['wc_id'],
                        'game_id'        => $slugToId[$p['game_slug']] ?? null,
                        'name'           => $p['name'],
                        'platform'       => $p['platform'],
                        'image_url'      => $p['image_url'] ?? null,
                        'category_raw'   => $p['category_raw'],
                        'last_synced_at' => now(),
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                    $bar->advance();
                }
                // upsert: insert si no existe, update si sí
                WooProduct::upsert(
                    $rows,
                    ['id'],
                    ['game_id', 'name', 'platform', 'image_url', 'category_raw', 'last_synced_at', 'updated_at']
                );
            }
        });
        $bar->finish();
        $this->newLine();

        // 3) ACCOUNTS + KEYS + ASSIGNMENTS
        $accounts = json_decode(file_get_contents("$path/accounts.json"), true);
        $this->info("Importando " . count($accounts) . " cuentas (con llaves y asignaciones)…");
        $bar = $this->output->createProgressBar(count($accounts));
        $stats = ['accounts' => 0, 'keys' => 0, 'assignments' => 0, 'skipped' => 0, 'sin_juego' => 0];

        DB::transaction(function () use ($accounts, $slugToId, $bar, &$stats) {
            foreach ($accounts as $a) {
                // Solo cuentas de tipo INDEPENDIENTE
                if ($this->mapType($a['account_type']) !== 'INDEPENDIENTE') {
                    $stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                // game_id puede venir null (cuenta sin juego o sin match auto).
                // Antes la salteábamos; ahora la importamos igual con game_id = null.
                $slug   = $a['game_slug'] ?? null;
                $gameId = $slug !== null ? ($slugToId[$slug] ?? null) : null;
                if (! $gameId) {
                    $stats['sin_juego']++;
                }

                $account = Account::create([
                    'game_id'         => $gameId,           // nullable
                    'platform'        => $this->mapPlatform($a['platform']),
                    'is_dual'         => $this->isDual($a['platform']),
                    'account_type'    => $this->mapType($a['account_type']),
                    'region'          => $a['region'] ?? 'OTRO',
                    'email'           => $a['email'] ?? 'sin-email@desconocido.local',
                    'password'        => $a['password'] ?? '',
                    'mail_email'      => $a['mail_email'] ?? null,
                    'mail_password'   => $a['mail_password'] ?? null,
                    'created_date'    => $a['created_date'],
                    'purchased_date'  => $a['purchased_date'],
                    'reset_date'      => $a['reset_date'],
                    'gamer_tag'       => $a['gamer_tag'] ?? null,
                    'birth_date'      => $a['birth_date'],
                    'status'          => 'active',
                ]);
                $stats['accounts']++;

                // Llaves (bulk insert)
                if (! empty($a['keys'])) {
                    $keyRows = array_map(fn($k) => [
                        'account_id' => $account->id,
                        'key_value'  => $k['value'],
                        'position'   => $k['position'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $a['keys']);
                    AccountKey::insert($keyRows);
                    $stats['keys'] += count($keyRows);
                }

                // Asignaciones (bulk insert)
                if (! empty($a['assignments'])) {
                    $assignRows = array_map(fn($x) => [
                        'account_id'     => $account->id,
                        'slot_number'    => $x['slot_number'],
                        'platform' => $x['platform'],
                        'customer_name'  => $x['customer_name'],
                        'customer_email' => $x['customer_email'],
                        'assigned_at'    => $x['assigned_at'],
                        'status'         => 'active',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ], $a['assignments']);
                    AccountAssignment::insert($assignRows);
                    $stats['assignments'] += count($assignRows);
                }

                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Importación completa");
        $this->table(['Tabla', 'Filas insertadas'], [
            ['games',               count($slugToId)],
            ['woo_products',        count($products)],
            ['accounts',            $stats['accounts']],
            ['  └ sin juego',       $stats['sin_juego']],
            ['account_keys',        $stats['keys']],
            ['account_assignments', $stats['assignments']],
            ['cuentas salteadas (no INDEPENDIENTE)', $stats['skipped']],
        ]);

        return self::SUCCESS;
    }

    /** Detecta si la cuenta es DUAL (todo lo que no sea PS4/PS5 se trata como DUAL). */
    private function isDual(?string $p): bool
    {
        $p = strtoupper(trim($p ?? ''));
        return ! in_array($p, ['PS5', 'PS4'], true);
    }

    /** Normaliza la plataforma: las cuentas DUAL se guardan como PS4. */
    private function mapPlatform(?string $p): string
    {
        if ($this->isDual($p)) {
            return 'PS4';
        }
        return strtoupper(trim($p));
    }

    private function mapType(?string $t): string
    {
        $t = strtoupper(trim($t ?? ''));
        return in_array($t, ['INDEPENDIENTE', 'MADRE', 'HIJA'], true) ? $t : 'INDEPENDIENTE';
    }
}