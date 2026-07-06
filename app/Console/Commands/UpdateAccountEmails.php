<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateAccountEmails extends Command
{
    /**
     * Uso:
     *   php artisan accounts:update-emails storage/app/cuentas_actualizar.csv --dry-run
     *   php artisan accounts:update-emails storage/app/cuentas_actualizar.csv
     *
     * El CSV debe tener 3 columnas (con cabecera):
     *   email_original, nuevo_email, nueva_password_mail
     */
    protected $signature = 'accounts:update-emails
                            {file : Ruta al CSV (email_original, nuevo_email, nueva_password_mail)}
                            {--dry-run : Simula los cambios sin guardar nada}
                            {--delimiter=, : Delimitador del CSV (por defecto coma)}
                            {--with-trashed : Incluir también cuentas borradas (soft-deleted)}';

    protected $description = 'Busca cuentas por email original y actualiza email, mail_email y mail_password (password NO cambia).';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("No encuentro el archivo: {$path}");
            return self::FAILURE;
        }

        $dryRun      = (bool) $this->option('dry-run');
        $delimiter   = $this->option('delimiter') ?: ',';
        $withTrashed = (bool) $this->option('with-trashed');

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error('No pude abrir el archivo.');
            return self::FAILURE;
        }

        // Saltar la fila de cabecera
        fgetcsv($handle, 0, $delimiter);

        $updated  = 0;   // cuentas modificadas
        $matched  = 0;   // filas del CSV que encontraron al menos una cuenta
        $notFound = 0;   // filas del CSV sin coincidencia
        $skipped  = 0;   // filas con datos incompletos
        $rowNum   = 1;

        $notFoundList = [];
        $multiMatch   = [];

        if ($dryRun) {
            $this->warn('── MODO DRY-RUN: no se guardará ningún cambio ──');
        }

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNum++;

                $original    = isset($row[0]) ? trim($row[0]) : '';
                $newEmail    = isset($row[1]) ? trim($row[1]) : '';
                $newMailPass = isset($row[2]) ? trim($row[2]) : '';

                if ($original === '' || $newEmail === '' || $newMailPass === '') {
                    $this->warn("Fila {$rowNum}: datos incompletos, se omite.");
                    $skipped++;
                    continue;
                }

                // Búsqueda case-insensitive e ignorando espacios alrededor.
                $query = Account::query()
                    ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower($original)]);

                if ($withTrashed) {
                    $query->withTrashed();
                }

                $accounts = $query->get();

                if ($accounts->isEmpty()) {
                    $notFound++;
                    $notFoundList[] = $original;
                    continue;
                }

                $matched++;

                if ($accounts->count() > 1) {
                    $multiMatch[] = "{$original} ({$accounts->count()} cuentas)";
                }

                foreach ($accounts as $account) {
                    $account->email         = $newEmail;
                    $account->mail_email    = $newEmail;
                    $account->mail_password = $newMailPass;
                    // password NO se toca

                    if (! $dryRun) {
                        $account->save();
                    }

                    $updated++;
                }

                $this->line("✔ {$original}  →  {$newEmail}");
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error en fila {$rowNum}: {$e->getMessage()}");
            fclose($handle);
            return self::FAILURE;
        }

        fclose($handle);

        $this->newLine();
        $this->info("Filas con coincidencia : {$matched}");
        $this->info("Cuentas actualizadas   : {$updated}");
        $this->info("No encontradas         : {$notFound}");
        $this->info("Omitidas (incompletas) : {$skipped}");

        if (! empty($multiMatch)) {
            $this->newLine();
            $this->warn('Emails con MÁS de una cuenta (se actualizaron todas):');
            foreach ($multiMatch as $m) {
                $this->line("  - {$m}");
            }
        }

        if (! empty($notFoundList)) {
            $this->newLine();
            $this->warn('Emails NO encontrados en accounts:');
            foreach ($notFoundList as $nf) {
                $this->line("  - {$nf}");
            }
        }

        return self::SUCCESS;
    }
}
