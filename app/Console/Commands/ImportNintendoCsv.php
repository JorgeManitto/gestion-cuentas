<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AccountAssignment;
use App\Models\AccountKey;
use App\Models\Game;
use App\Models\WooProduct;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportNintendoCsv extends Command
{
    protected $signature = 'import:nintendo-csv
        {--accounts= : Ruta a accounts_rows.csv (default: Downloads)}
        {--keys= : Ruta a activation_keys_rows.csv (default: Downloads)}
        {--games= : Ruta a games_rows.csv, SOLO para mapear (default: Downloads)}
        {--dry-run : No escribe nada; imprime resumen y reporte}
        {--fresh : Borra SOLO cuentas SWITCH/SWITCH_2 (y sus keys/assignments) antes de importar}';

    protected $description = 'Importa cuentas Nintendo desde los CSV de Supabase. Explota games[] en una cuenta por juego, mapea a juegos existentes (NO crea juegos) e importa las entregas como account_assignments.';

    /** Plataformas Nintendo tal como quedan en la BD (las usa --fresh). */
    private const NINTENDO_DB_PLATFORMS = ['SWITCH', 'SWITCH_2'];

    /** id_woo (string) => game_id local. */
    private array $wooToGameId = [];
    /** normalized_name => game_id local. */
    private array $normToGameId = [];
    /** games_rows.csv indexado por UUID de origen. */
    private array $gamesCsvById = [];
    /** UUIDs de juego que no resolvieron contra el catálogo local. */
    private array $unresolvedGames = [];

    public function handle(): int
    {
        $downloads = 'C:/Users/Jorge/Downloads';
        $accountsPath = $this->option('accounts') ?: "$downloads/accounts_rows.csv";
        $keysPath     = $this->option('keys')     ?: "$downloads/activation_keys_rows.csv";
        $gamesPath    = $this->option('games')    ?: "$downloads/games_rows.csv";

        foreach (['accounts' => $accountsPath, 'keys' => $keysPath, 'games' => $gamesPath] as $label => $p) {
            if (! file_exists($p)) {
                $this->error("No encontré el CSV de $label en: $p");
                return self::FAILURE;
            }
        }

        $dry = (bool) $this->option('dry-run');

        // ─────────── Cargar CSVs ───────────
        $accounts = $this->readCsv($accountsPath);
        $keys     = $this->readCsv($keysPath);
        $games    = $this->readCsv($gamesPath);

        foreach ($games as $g) {
            if (! empty($g['id'])) {
                $this->gamesCsvById[$g['id']] = $g;
            }
        }

        // Mapas de resolución contra el catálogo LOCAL (no se crea nada).
        $this->wooToGameId  = WooProduct::whereNotNull('game_id')->pluck('game_id', 'id')
            ->mapWithKeys(fn ($gid, $id) => [(string) $id => $gid])->all();
        $this->normToGameId = Game::pluck('id', 'normalized_name')->all();

        $this->info('CSV cargados:');
        $this->line('  cuentas:          ' . count($accounts));
        $this->line('  activation_keys:  ' . count($keys));
        $this->line('  games (catálogo):  ' . count($games));
        $this->newLine();

        // ─────────── Plan de cuentas (una fila por juego) ───────────
        // planned[i] = ['acctUuid','gameUuid'|null,'gameId'|null,'fields'=>[...],'poolKeys'=>[...]]
        $planned = [];
        $stats = [
            'cuentas_origen'      => count($accounts),
            'accounts_creadas'    => 0,
            'account_keys'        => 0,
            'assignments'         => 0,
            'assignments_orphan'  => 0,
            'game_null'           => 0,   // filas de cuenta sin juego (no resuelto o games=[])
            'sin_juego_alguno'    => 0,   // cuentas cuyo games[] estaba vacío
            'dup_colapsados'      => 0,   // UUIDs de games[] que colapsaron al mismo juego local
        ];

        // Entregas por cuenta de origen → para marcar used_at en el pool.
        $deliveredByAcct = [];   // acctUuid => [key_value => sent_at]
        foreach ($keys as $k) {
            $au = $k['account_id'] ?? '';
            $kv = trim((string) ($k['activation_key'] ?? ''));
            if ($au !== '' && $kv !== '') {
                $deliveredByAcct[$au][$kv] = $k['sent_at'] ?? null;
            }
        }

        // pairIndex mapea CADA UUID de origen a la fila destino (incluso los UUIDs
        // que colapsan a un juego ya visto), para no perder sus assignments.
        $pairIndex = [];      // "acctUuid|gameUuid" => índice en $planned
        $firstByAcct = [];    // acctUuid => índice en $planned

        foreach ($accounts as $a) {
            $acctUuid = $a['id'] ?? '';
            $fields   = $this->mapAccountFields($a);
            $poolKeys = $this->decodeArray($a['activation_keys'] ?? '');
            $gameUuids = $this->decodeArray($a['games'] ?? '');

            if (empty($gameUuids)) {
                // games[] vacío → una sola fila con game_id=null.
                $stats['sin_juego_alguno']++;
                $stats['game_null']++;
                $i = count($planned);
                $planned[] = ['acctUuid' => $acctUuid, 'gameUuid' => null, 'gameId' => null, 'fields' => $fields, 'poolKeys' => $poolKeys];
                $firstByAcct[$acctUuid] ??= $i;
                continue;
            }

            // Dedupe DENTRO de la cuenta: dos UUIDs distintos que resuelven al mismo
            // juego local (games CSV trae duplicados por variante de plataforma) deben
            // producir UNA sola fila. Clave: game_id si resolvió; si no, el UUID crudo
            // (así dos juegos faltantes DISTINTOS sí quedan separados).
            $seen = [];   // dedupKey => índice en $planned (dentro de esta cuenta)
            foreach ($gameUuids as $gu) {
                $gameId = $this->resolveGameId($gu);
                if ($gameId === null) {
                    $this->unresolvedGames[$gu] = $this->gamesCsvById[$gu]['title'] ?? '(colgado, no está en games CSV)';
                }
                $dedupKey = $gameId !== null ? 'g:' . $gameId : 'u:' . $gu;

                if (isset($seen[$dedupKey])) {
                    // Duplicado: no se crea fila; sus assignments van a la superviviente.
                    $pairIndex[$acctUuid . '|' . $gu] = $seen[$dedupKey];
                    $stats['dup_colapsados']++;
                    continue;
                }

                $i = count($planned);
                $planned[] = ['acctUuid' => $acctUuid, 'gameUuid' => $gu, 'gameId' => $gameId, 'fields' => $fields, 'poolKeys' => $poolKeys];
                $seen[$dedupKey] = $i;
                $pairIndex[$acctUuid . '|' . $gu] = $i;
                $firstByAcct[$acctUuid] ??= $i;
                if ($gameId === null) {
                    $stats['game_null']++;
                }
            }
        }
        $stats['accounts_creadas'] = count($planned);
        foreach ($planned as $p) {
            $stats['account_keys'] += count($p['poolKeys']);
        }

        // ─────────── Plan de assignments ───────────
        foreach ($keys as $k) {
            $au = $k['account_id'] ?? '';
            $gu = trim((string) ($k['game_id'] ?? ''));
            $target = $pairIndex[$au . '|' . $gu] ?? ($firstByAcct[$au] ?? null);
            if ($target === null) {
                $stats['assignments_orphan']++;
            } else {
                $stats['assignments']++;
            }
        }

        // ─────────── Reporte ───────────
        $this->printSummary($stats);

        $reportDir = storage_path('app/import-nintendo-csv');
        if (! is_dir($reportDir)) {
            @mkdir($reportDir, 0775, true);
        }
        $reportPath = "$reportDir/reporte.json";
        file_put_contents($reportPath, json_encode([
            'generated_at'      => now()->toIso8601String(),
            'dry_run'           => $dry,
            'stats'             => $stats,
            'juegos_no_resueltos' => array_map(
                fn ($uuid, $title) => ['uuid' => $uuid, 'title' => $title],
                array_keys($this->unresolvedGames),
                array_values($this->unresolvedGames)
            ),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->line("Reporte escrito en: $reportPath");
        $this->newLine();

        if ($dry) {
            $this->info('--dry-run: no se escribió nada.');
            return self::SUCCESS;
        }

        // ─────────── --fresh ───────────
        if ($this->option('fresh')) {
            $this->warn('--fresh: borrando SOLO cuentas Nintendo (SWITCH/SWITCH_2) y sus llaves/asignaciones…');
            $ids = DB::table('accounts')->whereIn('platform', self::NINTENDO_DB_PLATFORMS)->pluck('id');
            DB::table('account_assignments')->whereIn('account_id', $ids)->delete();
            DB::table('account_keys')->whereIn('account_id', $ids)->delete();
            DB::table('accounts')->whereIn('platform', self::NINTENDO_DB_PLATFORMS)->delete();
        }

        // ─────────── Escritura ───────────
        $this->info('Escribiendo en la base…');
        $bar = $this->output->createProgressBar(count($planned) + count($keys));

        DB::transaction(function () use ($planned, $keys, $deliveredByAcct, $pairIndex, $firstByAcct, $bar) {
            // Mapa índice-en-planned → account.id real (para enlazar assignments).
            $plannedIdToAccountId = [];

            foreach ($planned as $i => $p) {
                $account = Account::create(array_merge($p['fields'], ['game_id' => $p['gameId']]));
                $plannedIdToAccountId[$i] = $account->id;

                // Pool de llaves de la cuenta física, replicado en esta fila-por-juego.
                if ($p['poolKeys']) {
                    $delivered = $deliveredByAcct[$p['acctUuid']] ?? [];
                    $rows = [];
                    foreach (array_values($p['poolKeys']) as $pos => $kv) {
                        $kv = trim((string) $kv);
                        $usedAt = isset($delivered[$kv]) ? $this->ts($delivered[$kv]) : null;
                        $rows[] = [
                            'account_id' => $account->id,
                            'key_value'  => Str::limit($kv, 64, ''),
                            'position'   => $pos + 1,
                            'used_at'    => $usedAt,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    AccountKey::insert($rows);
                }
                $bar->advance();
            }

            // Assignments: slot_number incremental por account.id destino.
            $slotByAccount = [];
            foreach ($keys as $k) {
                $au = $k['account_id'] ?? '';
                $gu = trim((string) ($k['game_id'] ?? ''));
                $idx = $pairIndex[$au . '|' . $gu] ?? ($firstByAcct[$au] ?? null);
                if ($idx === null || ! isset($plannedIdToAccountId[$idx])) {
                    $bar->advance();
                    continue;   // orphan: cuenta de origen no importada
                }
                $accountId = $plannedIdToAccountId[$idx];
                $slot = ($slotByAccount[$accountId] = ($slotByAccount[$accountId] ?? 0) + 1);

                AccountAssignment::create([
                    'account_id'     => $accountId,
                    'slot_number'    => $slot,
                    'platform'       => $planned[$idx]['fields']['platform'],
                    'key_value'      => Str::limit(trim((string) ($k['activation_key'] ?? '')), 128, ''),
                    'customer_name'  => $k['customer_name']  ?? null,
                    'customer_email' => $k['customer_email'] ?? null,
                    'assigned_at'    => $this->date($k['sent_at'] ?? null),
                    'status'         => 'active',
                    'woo_order_id'   => null, // order_id de origen es UUID; no cabe en unsignedBigInteger
                ]);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info('✓ Importación completa.');
        $this->printSummary($stats);
        $this->line("Reporte: $reportPath");

        return self::SUCCESS;
    }

    // ──────────────────────── Helpers ────────────────────────

    /** Lee un CSV a array asociativo usando fgetcsv (respeta comas dentro de JSON). */
    private function readCsv(string $file): array
    {
        $out = [];
        $fh = fopen($file, 'r');
        $header = fgetcsv($fh);
        while (($r = fgetcsv($fh)) !== false) {
            if ($r === [null] || count($r) < count($header)) {
                continue;
            }
            $out[] = array_combine($header, $r);
        }
        fclose($fh);
        return $out;
    }

    /** Decodifica una columna JSON tipo '["a","b"]' a array; [] si vacío/ inválido. */
    private function decodeArray(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '[]') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values(array_filter($decoded, fn ($v) => $v !== null && $v !== '')) : [];
    }

    /** UUID de juego de origen → game_id local, o null (id_woo primero, luego título). */
    private function resolveGameId(string $uuid): ?int
    {
        $g = $this->gamesCsvById[$uuid] ?? null;
        if (! $g) {
            return null;   // colgado: ni siquiera está en games CSV
        }
        $idWoo = trim((string) ($g['id_woo'] ?? ''));
        if ($idWoo !== '' && isset($this->wooToGameId[$idWoo])) {
            return (int) $this->wooToGameId[$idWoo];
        }
        $norm = (string) Str::of($g['title'] ?? '')->lower()->squish();
        if ($norm !== '' && isset($this->normToGameId[$norm])) {
            return (int) $this->normToGameId[$norm];
        }
        return null;
    }

    /** Mapea una fila de accounts_rows.csv a los campos de la tabla accounts (sin game_id). */
    private function mapAccountFields(array $a): array
    {
        [$mailEmail, $mailPass] = $this->splitInternalMail($a['internal_email_password'] ?? null);
        [$status, $disableReason] = $this->mapStatus($a);

        return [
            'platform'       => $this->mapPlatform($a),
            'is_dual'        => filter_var($a['is_dual_model'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'account_type'   => 'INDEPENDIENTE',
            'region'         => $this->normalizeRegion($a['region'] ?? null),
            'email'          => $a['email'] ?: 'sin-email@desconocido.local',
            'password'       => $a['password'] ?? '',
            'mail_email'     => $mailEmail,
            'mail_password'  => $mailPass,
            'created_date'   => $this->date($a['created_at'] ?? null),
            'purchased_date' => $this->date($a['purchase_date'] ?? null),
            'reset_date'     => $this->date($a['next_reset_date'] ?? null),
            'gamer_tag'      => ($a['online_id'] ?? '') ?: null,
            'birth_date'     => $this->date($a['birth_date'] ?? null),
            'status'         => $status,
            'disable_reason' => $disableReason,
            'notes'          => ($a['notes'] ?? '') ?: null,
        ];
    }

    /** console_model → plataforma local. dual/switch1/vacío → SWITCH; switch2 → SWITCH_2. */
    private function mapPlatform(array $a): string
    {
        $model = strtolower(trim((string) ($a['console_model'] ?? '')));
        return str_contains($model, 'switch2') || str_contains($model, 'switch 2')
            ? 'SWITCH_2'
            : 'SWITCH';
    }

    /** status_pivot (o status) → [status enum destino, disable_reason]. */
    private function mapStatus(array $a): array
    {
        $pivot  = strtolower(trim((string) ($a['status_pivot'] ?? '')));
        $status = strtolower(trim((string) ($a['status'] ?? '')));

        return match (true) {
            $pivot === 'disponible' || $status === 'available' || $status === 'in-use' => ['active', null],
            $pivot === 'descanso'                                                       => ['reset', 'descanso'],
            $pivot === 'baneada'                                                        => ['blocked', 'baneada'],
            $pivot === 'robada'                                                         => ['blocked', 'robada'],
            $status === 'disabled'                                                      => ['blocked', 'otro'],
            default                                                                     => ['active', null],
        };
    }

    /** Normaliza las variantes de región ("hong kon", "Hong kong" → HONG KONG). */
    private function normalizeRegion(?string $raw): string
    {
        $r = strtoupper(trim((string) $raw));
        if ($r === '') {
            return 'OTRO';
        }
        if (str_starts_with($r, 'HON')) {
            return 'HONG KONG';
        }
        return match ($r) {
            'BRAZIL' => 'BRASIL',
            default  => $r,
        };
    }

    /** "correo@dominio / clave" → [mail_email, mail_password]. */
    private function splitInternalMail(?string $raw): array
    {
        if (! $raw) {
            return [null, null];
        }
        $parts = array_map('trim', explode('/', $raw, 2));
        return [$parts[0] ?: null, $parts[1] ?? null];
    }

    /** Timestamp de Supabase → fecha (Y-m-d) o null. */
    private function date(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Timestamp de Supabase → datetime (Y-m-d H:i:s) o null. */
    private function ts(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        try {
            return Carbon::parse($raw)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function printSummary(array $stats): void
    {
        $this->table(['Métrica', 'Valor'], [
            ['cuentas en el CSV',              $stats['cuentas_origen']],
            ['accounts a crear (por juego)',   $stats['accounts_creadas']],
            ['  · de esas, sin juego (null)',  $stats['game_null']],
            ['  · cuentas con games[] vacío',  $stats['sin_juego_alguno']],
            ['duplicados colapsados',          $stats['dup_colapsados']],
            ['account_keys (pool)',            $stats['account_keys']],
            ['account_assignments',            $stats['assignments']],
            ['assignments huérfanas',          $stats['assignments_orphan']],
            ['juegos NO resueltos (únicos)',   count($this->unresolvedGames)],
        ]);
    }
}
