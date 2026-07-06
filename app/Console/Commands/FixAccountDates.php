<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAccountDates extends Command
{
    protected $signature = 'fix:account-dates
                            {--path=storage/app/import : Carpeta con accounts.json}
                            {--dry-run : Mostrar lo que se haría sin escribir en la BD}';

    protected $description = 'Corrige las fechas de accounts (created/purchased/reset/birth) volviéndolas a leer del JSON original. NO toca ningún otro campo.';

    /** Columnas de fecha en la tabla accounts  ↔  claves en el JSON */
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

        // 1) Agrupamos por email las fechas correctas del JSON.
        //    Solo INDEPENDIENTE, que son las únicas que se importaron.
        $byEmail   = [];   // email => ['fields' => [...], 'signature' => '...']
        $conflicts = [];   // email => true  (mismo email con fechas distintas)
        $badDates  = [];   // [email, columna, valor]  que no se pudo parsear

        foreach ($accounts as $a) {
            $type = strtoupper(trim($a['account_type'] ?? ''));
            if ($type !== 'INDEPENDIENTE') {
                continue;
            }

            $email = $a['email'] ?? null;
            if (! $email) {
                // Sin email no hay forma de matchear la fila en la BD.
                continue;
            }

            $fields = [];
            foreach ($this->dateFields as $col => $jsonKey) {
                $fields[$col] = $this->normalizeDate($a[$jsonKey] ?? null, $email, $col, $badDates);
            }

            $signature = json_encode($fields);

            if (isset($byEmail[$email]) && $byEmail[$email]['signature'] !== $signature) {
                // El mismo email aparece dos veces con fechas distintas:
                // no sabemos cuál fila es cuál, lo dejamos para revisión manual.
                $conflicts[$email] = true;
            } else {
                $byEmail[$email] = ['fields' => $fields, 'signature' => $signature];
            }
        }

        foreach (array_keys($conflicts) as $email) {
            unset($byEmail[$email]);
        }

        $this->info('Emails con fechas a corregir: ' . count($byEmail));
        $bar = $this->output->createProgressBar(count($byEmail));

        $stats = ['updated_rows' => 0, 'emails_ok' => 0, 'sin_match' => 0];

        $apply = function () use ($byEmail, $bar, &$stats, $dryRun) {
            foreach ($byEmail as $email => $data) {
                $query = DB::table('accounts')->where('email', $email);
                $count = (clone $query)->count();

                if ($count === 0) {
                    $stats['sin_match']++;
                    $bar->advance();
                    continue;
                }

                if (! $dryRun) {
                    // A propósito usamos query builder y NO el modelo:
                    // así no pasa por los casts/mutators que invierten día/mes.
                    // Tampoco toca updated_at ni ninguna otra columna.
                    $query->update($data['fields']);
                }

                $stats['updated_rows'] += $count;
                $stats['emails_ok']++;
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

        $this->info($dryRun ? '✓ Dry-run completo (no se escribió nada)' : '✓ Fechas corregidas');
        $this->table(['Concepto', 'Cantidad'], [
            ['Emails procesados',              count($byEmail)],
            ['Filas actualizadas',             $stats['updated_rows']],
            ['Emails sin fila en la BD',       $stats['sin_match']],
            ['Emails en conflicto (saltados)', count($conflicts)],
            ['Fechas inválidas (a null)',      count($badDates)],
        ]);

        if ($conflicts) {
            $this->newLine();
            $this->warn('Emails con fechas distintas entre entradas duplicadas (revisar a mano):');
            foreach (array_keys($conflicts) as $email) {
                $this->line("  - $email");
            }
        }

        if ($badDates) {
            $this->newLine();
            $this->warn('Fechas que no respetaban año-mes-día (se dejaron en null):');
            foreach ($badDates as [$email, $col, $val]) {
                $this->line("  - $email | $col = " . var_export($val, true));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Valida que la fecha del JSON sea año-mes-día y la devuelve normalizada a 'Y-m-d'.
     * Devuelve null si viene null/vacía o si no es una fecha válida.
     */
    private function normalizeDate($raw, string $email, string $col, array &$badDates): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        // El JSON viene como AÑO-MES-DÍA. Validamos sin depender de Carbon
        // para no arriesgar interpretaciones ambiguas.
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
