<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillTypeConsole extends Command
{
    protected $signature = 'fix:type-console
                            {--dry-run : Mostrar lo que se haría sin escribir en la BD}
                            {--overwrite : Sobrescribir type_console aunque ya tenga valor}';

    protected $description = 'Rellena type_console (PLAYSTATION/XBOX/NINTENDO/STEAM) a partir de la columna platform.';

    /** platform (interno) → type_console (marca). Se compara en MAYÚSCULAS. */
    private array $map = [
        'PS4'         => 'PLAYSTATION',
        'PS5'         => 'PLAYSTATION',
        'XBOX_ONE'    => 'XBOX',
        'XBOX_SERIES' => 'XBOX',
        'SWITCH'      => 'NINTENDO',
        'SWITCH_2'    => 'NINTENDO',
        'STEAM'       => 'STEAM',
    ];

    public function handle(): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');

        if ($dryRun) {
            $this->warn('--dry-run: no se va a escribir nada en la base de datos.');
        }

        // Solo tocamos filas sin type_console, salvo que se pida --overwrite.
        $base = fn () => DB::table('accounts')
            ->when(! $overwrite, fn ($q) => $q->where(function ($w) {
                $w->whereNull('type_console')->orWhere('type_console', '');
            }));

        $stats     = [];   // type_console => filas actualizadas
        $unmapped  = [];   // platform (normalizado) => cantidad, sin mapeo
        $noPlatform = 0;   // filas candidatas sin platform

        $rows = $base()->get(['id', 'platform']);

        $this->info('Cuentas candidatas: ' . $rows->count());

        $apply = function () use ($rows, $overwrite, $dryRun, &$stats, &$unmapped, &$noPlatform) {
            foreach ($rows as $row) {
                $platform = strtoupper(trim($row->platform ?? ''));

                if ($platform === '') {
                    $noPlatform++;
                    continue;
                }

                $console = $this->map[$platform] ?? null;

                if ($console === null) {
                    // DUAL u otros valores ambiguos/desconocidos: no adivinamos.
                    $unmapped[$platform] = ($unmapped[$platform] ?? 0) + 1;
                    continue;
                }

                if (! $dryRun) {
                    DB::table('accounts')
                        ->where('id', $row->id)
                        ->update(['type_console' => $console]);
                }

                $stats[$console] = ($stats[$console] ?? 0) + 1;
            }
        };

        if ($dryRun) {
            $apply();
        } else {
            DB::transaction($apply);
        }

        $this->newLine();
        $this->info($dryRun ? '✓ Dry-run completo (no se escribió nada)' : '✓ type_console actualizado');

        $resumen = [];
        foreach ($this->map as $console) {
            if (isset($stats[$console])) {
                $resumen[$console] = ['Consola ' . $console, $stats[$console]];
            }
        }
        $resumen['_total'] = ['TOTAL actualizadas', array_sum($stats)];
        $this->table(['Concepto', 'Cantidad'], array_values($resumen));

        if ($noPlatform) {
            $this->newLine();
            $this->warn("Filas candidatas sin platform (saltadas): {$noPlatform}");
        }

        if ($unmapped) {
            $this->newLine();
            $this->warn('Platforms sin mapeo (p. ej. DUAL) — se dejaron sin tocar:');
            foreach ($unmapped as $platform => $count) {
                $this->line("  - {$platform}: {$count}");
            }
        }

        return self::SUCCESS;
    }
}
