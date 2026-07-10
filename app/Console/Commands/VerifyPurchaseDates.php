<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Verifica (y opcionalmente corrige) las fechas de las cuentas contra los Excel
 * originales (Madres.xlsx / JuegosPS.xlsx), que son la FUENTE DE VERDAD.
 *
 * El problema: al importar, muchas fechas —sobre todo "Fecha Compra"— quedaron
 * guardadas en mes/día/año cuando debían ser día/mes/año (quedaron con el día y
 * el mes invertidos).
 *
 * Cómo se lee la verdad desde el Excel (igual criterio que openpyxl):
 *   - Celdas de TEXTO ("22/06/2025")  → se leen DÍA-PRIMERO (d/m/Y).
 *   - Celdas de FECHA real (serial)   → se toman tal cual (ya son una fecha absoluta).
 *
 * Por defecto NO escribe nada: reporta las diferencias. Con --fix las corrige.
 * Al escribir usa el query builder (no el modelo) para no pasar por los casts.
 */
class VerifyPurchaseDates extends Command
{
    protected $signature = 'accounts:verify-dates
        {--path=C:/proyectos/pip-woo-gestion-cuentas/inputs : Carpeta con Madres.xlsx y JuegosPS.xlsx}
        {--fix : Corrige las fechas mal guardadas en la BD (por defecto solo reporta)}
        {--swapped-only : Al corregir, aplica SOLO las inversiones día/mes (deja las "distinta" para revisión manual)}
        {--only-purchased : Verifica únicamente la fecha de compra}
        {--limit=60 : Cuántas filas mostrar en la tabla de diferencias}';

    protected $description = 'Compara las fechas de las cuentas contra los Excel originales (por email) y detecta/corrige las que quedaron con día/mes invertidos.';

    /** Columna del Excel (1-index) → columna de la BD. */
    private array $dateCols = [
        8  => 'created_date',    // H  "Creacion"
        9  => 'purchased_date',  // I  "Fecha Compra"
        10 => 'reset_date',      // J  "Reset"
        22 => 'birth_date',      // V  "Nacimiento"
    ];

    private const EMAIL_COL = 5;   // E

    private array $files = ['Madres.xlsx', 'JuegosPS.xlsx'];

    public function handle(): int
    {
        $path = rtrim(str_replace('\\', '/', $this->option('path')), '/');

        if ($this->option('only-purchased')) {
            $this->dateCols = [9 => 'purchased_date'];
        }

        // ── 1) Leer los Excel → mapa email => fechas correctas ──
        $truth      = [];   // email => [col => 'Y-m-d' | null]
        $conflicts  = [];   // email => true  (mismo email con fechas distintas)
        $badText    = [];   // [file, email, campo, valor]  texto no parseable
        $rowsRead   = 0;

        foreach ($this->files as $file) {
            $full = "$path/$file";
            if (! is_file($full)) {
                $this->error("No encontré $full");
                return self::FAILURE;
            }

            $this->line("Leyendo $file …");
            [$read, $bad] = $this->readExcel($full, $truth, $conflicts, $file);
            $rowsRead += $read;
            $badText = array_merge($badText, $bad);
        }

        foreach (array_keys($conflicts) as $email) {
            unset($truth[$email]);   // no sabemos cuál fila es cuál → fuera
        }

        $this->info("Filas leídas de los Excel:        $rowsRead");
        $this->info("Emails con fecha de referencia:   " . count($truth));
        $this->info("Emails en conflicto (saltados):   " . count($conflicts));
        $this->newLine();

        // ── 2) Comparar contra la BD ──
        $diffs    = [];   // filas con diferencias
        $stats    = ['sin_match' => 0, 'ok' => 0, 'con_diff' => 0];
        $fieldHit = array_fill_keys(array_values($this->dateCols), 0);
        $swapped  = 0;

        foreach ($truth as $email => $fields) {
            $row = DB::table('accounts')->where('email', $email)->first();
            if (! $row) {
                $stats['sin_match']++;
                continue;
            }

            $rowDiffs = [];       // lo que se escribe en la BD
            $rowHadDiff = false;  // hubo alguna diferencia (aunque no se aplique)
            foreach ($fields as $col => $correct) {
                if ($correct === null) {
                    continue;   // el Excel no tiene fecha → no tocamos lo que haya en la BD
                }
                $current = $this->dbDate($row->{$col} ?? null);
                if ($current === $correct) {
                    continue;
                }

                $type = $this->isSwapped($current, $correct) ? 'invertida' : 'distinta';
                if ($type === 'invertida') {
                    $swapped++;
                }
                $fieldHit[$col]++;
                $rowHadDiff = true;

                // Con --swapped-only solo aplicamos las inversiones limpias día/mes.
                if (! $this->option('swapped-only') || $type === 'invertida') {
                    $rowDiffs[$col] = $correct;
                }

                $diffs[] = [
                    'email'   => $email,
                    'campo'   => $col,
                    'en_bd'   => $current ?? 'NULL',
                    'correcto'=> $correct,
                    'tipo'    => $type,
                ];
            }

            if ($rowHadDiff) {
                $stats['con_diff']++;
            } else {
                $stats['ok']++;
            }

            if ($rowDiffs && $this->option('fix')) {
                DB::table('accounts')->where('email', $email)->update($rowDiffs);
            }
        }

        // ── 3) Reporte ──
        $this->renderReport($diffs, $stats, $fieldHit, $swapped, $conflicts, $badText, $path);

        return self::SUCCESS;
    }

    // ──────────────────────── LECTURA DEL XLSX (sin dependencias) ────────────────────────

    /**
     * Lee un .xlsx crudo (zip + XML) y llena $truth[email] = [col => fecha].
     * @return array{0:int,1:array} [filas leídas, textos no parseables]
     */
    private function readExcel(string $file, array &$truth, array &$conflicts, string $label): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            $this->error("No pude abrir el zip de $file");
            return [0, []];
        }

        $shared = $this->readSharedStrings($zip);
        $sheet  = $this->cleanXml($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();

        $xml = simplexml_load_string($sheet);
        if ($xml === false) {
            $this->error("No pude parsear la hoja de $file");
            return [0, []];
        }

        $read = 0;
        $bad  = [];

        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];                       // ej "I2"
                $idx = $this->colIndex(rtrim($ref, '0123456789'));
                $cells[$idx] = $this->cellValue($c, $shared);
            }

            $email = strtolower(trim((string) ($cells[self::EMAIL_COL] ?? '')));
            if (! str_contains($email, '@')) {
                continue;   // header / fila sin email
            }
            $read++;

            $fields = [];
            foreach ($this->dateCols as $col => $dbCol) {
                $fields[$dbCol] = $this->cellToDate($cells[$col] ?? null, $email, $dbCol, $label, $bad);
            }

            $sig = json_encode($fields);
            if (isset($truth[$email]) && json_encode($truth[$email]) !== $sig) {
                $conflicts[$email] = true;
            } else {
                $truth[$email] = $fields;
            }
        }

        return [$read, $bad];
    }

    /** Lee sharedStrings.xml → array indexado de textos. */
    private function readSharedStrings(\ZipArchive $zip): array
    {
        $raw = $zip->getFromName('xl/sharedStrings.xml');
        if ($raw === false) {
            return [];
        }
        $xml = simplexml_load_string($this->cleanXml($raw));
        if ($xml === false) {
            return [];
        }

        $out = [];
        foreach ($xml->si as $si) {
            // Puede ser <si><t>..</t></si> o rich text <si><r><t>..</t></r>..</si>
            $text = '';
            foreach ($si->xpath('.//t') as $t) {
                $text .= (string) $t;
            }
            $out[] = $text;
        }
        return $out;
    }

    /** Valor de una celda: resuelve shared string, inline string o número/serial. */
    private function cellValue(\SimpleXMLElement $c, array $shared): ?string
    {
        $type = (string) $c['t'];

        if ($type === 's') {                        // shared string
            $i = (int) $c->v;
            return $shared[$i] ?? null;
        }
        if ($type === 'inlineStr') {
            $text = '';
            foreach ($c->xpath('.//t') as $t) {
                $text .= (string) $t;
            }
            return $text;
        }
        // número (posible serial de fecha) o string de fórmula
        $v = (string) $c->v;
        return $v === '' ? null : $v;
    }

    /** Quita namespaces por defecto y atributos con prefijo para que SimpleXML no falle. */
    private function cleanXml(?string $xml): string
    {
        if ($xml === null || $xml === false) {
            return '<root/>';
        }
        $xml = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $xml);   // fuera declaraciones de namespace
        $xml = preg_replace('/\s\w+:\w+="[^"]*"/', '', $xml);        // fuera atributos con prefijo (x14ac:*, r:*)
        return $xml;
    }

    /** "AB12" → índice de columna 1-based (A=1). */
    private function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n;
    }

    // ──────────────────────── NORMALIZACIÓN DE FECHAS ────────────────────────

    /**
     * Convierte el valor crudo de una celda a 'Y-m-d' aplicando el criterio correcto:
     *   - texto "d/m/Y"  → día primero
     *   - serial numérico → fecha absoluta de Excel (base 1899-12-30)
     * Devuelve null si está vacío; registra en $bad lo que no se pueda interpretar.
     */
    private function cellToDate(?string $raw, string $email, string $col, string $file, array &$bad): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);

        // Serial de fecha de Excel (entero o decimal, sin barras).
        //
        // OJO: estas celdas son fechas que se tipearon en d/m/a pero una
        // herramienta con locale US (m/d/a) las convirtió a fecha real
        // INVIRTIENDO día↔mes (las de día>12 no se pudieron parsear y quedaron
        // como texto). Por eso, si el "día" de Excel es <=12, la fecha vino
        // invertida y hay que des-invertirla; si es >12 es inequívoca y va tal cual.
        if (is_numeric($raw)) {
            $serial = (int) floor((float) $raw);
            if ($serial <= 0) {
                return null;
            }
            $dt = (new \DateTime('1899-12-30'))->modify("+$serial days");
            $y  = (int) $dt->format('Y');
            $em = (int) $dt->format('n');   // mes según Excel
            $ed = (int) $dt->format('j');   // día según Excel

            return $ed <= 12
                ? sprintf('%04d-%02d-%02d', $y, $ed, $em)   // invertida → mes=ed, día=em
                : sprintf('%04d-%02d-%02d', $y, $em, $ed);  // inequívoca → tal cual
        }

        // Texto día/mes/año.
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $raw, $m)) {
            [, $d, $mo, $y] = $m;
            if (checkdate((int) $mo, (int) $d, (int) $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        // Texto ya en ISO (por las dudas).
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $raw, $m)) {
            [, $y, $mo, $d] = $m;
            if (checkdate((int) $mo, (int) $d, (int) $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }

        $bad[] = [$file, $email, $col, $raw];
        return null;
    }

    /** Normaliza lo que haya en la BD a 'Y-m-d' o null (viene como datetime string). */
    private function dbDate($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        return substr((string) $raw, 0, 10);
    }

    /** ¿$current es el mismo día que $correct pero con día y mes intercambiados? */
    private function isSwapped(?string $current, string $correct): bool
    {
        if ($current === null) {
            return false;
        }
        [$cy, $cm, $cd] = array_map('intval', explode('-', $current) + [0, 0, 0]);
        [$ty, $tm, $td] = array_map('intval', explode('-', $correct) + [0, 0, 0]);

        return $cy === $ty && $cm === $td && $cd === $tm && $tm !== $td;
    }

    // ──────────────────────── REPORTE ────────────────────────

    private function renderReport(array $diffs, array $stats, array $fieldHit, int $swapped, array $conflicts, array $badText, string $path): void
    {
        $this->newLine();
        $this->info($this->option('fix') ? '✓ Fechas corregidas' : '✓ Verificación (dry-run, no se escribió nada)');
        $this->table(['Concepto', 'Cantidad'], [
            ['Cuentas OK (ya correctas)',        $stats['ok']],
            ['Cuentas con diferencias',          $stats['con_diff']],
            ['  └ campos invertidos (día/mes)',  $swapped],
            ['Emails sin cuenta en la BD',       $stats['sin_match']],
            ['Emails en conflicto (saltados)',   count($conflicts)],
            ['Textos de fecha no parseables',    count($badText)],
        ]);

        if ($fieldHit) {
            $this->newLine();
            $this->line('Diferencias por campo:');
            foreach ($fieldHit as $col => $n) {
                if ($n > 0) {
                    $this->line("  - $col: $n");
                }
            }
        }

        if ($diffs) {
            $limit = (int) $this->option('limit');
            $this->newLine();
            $this->warn(($this->option('fix') ? 'Corregidas' : 'A corregir') . ' (' . count($diffs) . ", muestro hasta $limit):");
            $this->table(
                ['Email', 'Campo', 'En BD', 'Correcto (Excel)', 'Tipo'],
                array_map(fn ($d) => [$d['email'], $d['campo'], $d['en_bd'], $d['correcto'], $d['tipo']],
                    array_slice($diffs, 0, $limit))
            );
        }

        // Reporte completo a disco.
        $reportPath = storage_path('app/verificacion-fechas-' . date('Ymd-His') . '.json');
        file_put_contents($reportPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'aplicado'     => (bool) $this->option('fix'),
            'stats'        => $stats + ['invertidas' => $swapped],
            'diferencias'  => $diffs,
            'conflictos'   => array_keys($conflicts),
            'texto_malo'   => $badText,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->line("Reporte completo: $reportPath");

        if (! $this->option('fix') && $diffs) {
            $this->newLine();
            $this->comment('Volvé a correr con --fix para aplicar las correcciones.');
        }
    }
}
