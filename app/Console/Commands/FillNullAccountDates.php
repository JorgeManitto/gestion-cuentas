<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FillNullAccountDates extends Command
{
    protected $signature = 'fill:account-dates
                            {--path=storage/app/import : Carpeta con accounts.json}
                            {--dry-run : Mostrar qué se rellenaría sin escribir en la BD}';

    protected $description = 'Rellena SOLO las fechas que están en NULL en accounts, leyéndolas del accounts.json. No toca ninguna otra columna ni las fechas que ya tienen valor.';

    /** Columnas de fecha en la tabla accounts ↔ claves en el JSON */
    private array $dateFields = [
        'created_date'   => 'created_date',
        'purchased_date' => 'purchased_date',
        'reset_date'     => 'reset_date',
        'birth_date'     => 'birth_date',
    ];

    public function handle(): int
    {
        $path = base_path($this->option('path'));
        $file = "$path/accounts.json";

        if (! file_exists($file)) {
            $this->error("No encontré $file");
            return self::FAILURE;
        }

        $accounts = json_decode(file_get_contents($file), true);
        if (! is_array($accounts)) {
            $this->error("No pude parsear el JSON de $file");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('--dry-run: no se va a escribir nada en la base de datos.');
        }

        // 1) email => ['fields' => [col => 'Y-m-d'|null], 'sig' => '...']
        //    Solo INDEPENDIENTE (las únicas importadas) y con email (única clave de match).
        $byEmail   = [];
        $conflicts = [];   // email duplicado con fechas distintas → no sabemos cuál es cuál
        $badDates  = [];   // [email, columna, valor] que no respetaba año-mes-día

        foreach ($accounts as $a) {
            if (strtoupper(trim($a['account_type'] ?? '')) !== 'INDEPENDIENTE') {
                continue;
            }

            $email = $a['email'] ?? null;
            if (! $email) {
                continue;
            }

            $fields = [];
            foreach ($this->dateFields as $col => $jsonKey) {
                $fields[$col] = $this->normalizeDate($a[$jsonKey] ?? null, $email, $col, $badDates);
            }

            $sig = json_encode($fields);
            if (isset($byEmail[$email]) && $byEmail[$email]['sig'] !== $sig) {
                $conflicts[$email] = true;
            } else {
                $byEmail[$email] = ['fields' => $fields, 'sig' => $sig];
            }
        }

        foreach (array_keys($conflicts) as $email) {
            unset($byEmail[$email]);
        }

        $bar = $this->output->createProgressBar(count($byEmail));

        $stats = [
            'rows_touched'   => 0,
            'cols_filled'    => 0,
            'sin_match'      => 0,
            'nada_que_hacer' => 0,
        ];
        $perColumn = array_fill_keys(array_keys($this->dateFields), 0);

        $apply = function () use ($byEmail, $bar, &$stats, &$perColumn, $dryRun) {
            foreach ($byEmail as $email => $data) {
                $rows = DB::table('accounts')
                    ->where('email', $email)
                    ->get(['id', 'created_date', 'purchased_date', 'reset_date', 'birth_date']);

                if ($rows->isEmpty()) {
                    $stats['sin_match']++;
                    $bar->advance();
                    continue;
                }

                $touchedSomething = false;

                foreach ($rows as $row) {
                    $update = [];
                    foreach ($this->dateFields as $col => $_) {
                        // Única condición: la BD lo tiene en NULL Y el JSON trae fecha válida.
                        if ($row->{$col} === null && $data['fields'][$col] !== null) {
                            $update[$col] = $data['fields'][$col];
                        }
                    }

                    if (empty($update)) {
                        continue;   // esta fila no tiene nada que rellenar
                    }

                    if (! $dryRun) {
                        // Query builder a propósito: escribe el literal Y-m-d sin pasar
                        // por casts/mutators, y solo toca las columnas de $update.
                        DB::table('accounts')->where('id', $row->id)->update($update);
                    }

                    $stats['rows_touched']++;
                    $stats['cols_filled'] += count($update);
                    foreach ($update as $col => $_) {
                        $perColumn[$col]++;
                    }
                    $touchedSomething = true;
                }

                if (! $touchedSomething) {
                    $stats['nada_que_hacer']++;
                }

                $bar->advance();
            }
        };

        if ($dryRun) {
            $apply();
        } else {
            DB::transaction($apply);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info($dryRun ? '✓ Dry-run completo (no se escribió nada)' : '✓ Fechas NULL rellenadas');
        $this->table(['Concepto', 'Cantidad'], [
            ['Filas modificadas',          $stats['rows_touched']],
            ['Fechas rellenadas (celdas)', $stats['cols_filled']],
            ['  └ created_date',           $perColumn['created_date']],
            ['  └ purchased_date',         $perColumn['purchased_date']],
            ['  └ reset_date',             $perColumn['reset_date']],
            ['  └ birth_date',             $perColumn['birth_date']],
            ['Emails sin fila en la BD',   $stats['sin_match']],
            ['Emails sin nada que llenar', $stats['nada_que_hacer']],
            ['Emails en conflicto',        count($conflicts)],
            ['Fechas inválidas en JSON',   count($badDates)],
        ]);

        if ($conflicts) {
            $this->newLine();
            $this->warn('Emails duplicados con fechas distintas (saltados, revisar a mano):');
            foreach (array_keys($conflicts) as $e) {
                $this->line("  - $e");
            }
        }

        if ($badDates) {
            $this->newLine();
            $this->warn('Valores del JSON que no respetaban año-mes-día (ignorados):');
            foreach ($badDates as [$email, $col, $val]) {
                $this->line("  - $email | $col = " . var_export($val, true));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Valida que la fecha del JSON sea año-mes-día real y la devuelve como 'Y-m-d'.
     * Devuelve null si viene vacía o no es una fecha válida (en ese caso NO se rellena).
     */
    private function normalizeDate($raw, string $email, string $col, array &$badDates): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', trim($raw), $m)) {
            $badDates[] = [$email, $col, $raw];
            return null;
        }

        [, $y, $mo, $d] = $m;

        if (! checkdate((int) $mo, (int) $d, (int) $y)) {
            $badDates[] = [$email, $col, $raw];
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
}
