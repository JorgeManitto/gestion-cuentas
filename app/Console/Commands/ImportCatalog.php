<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\WooProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportCatalog extends Command
{
    protected $signature = 'import:catalog
                            {--path=storage/app/import : Carpeta con games.json y woo_products.json}
                            {--fresh : Vaciar las tablas antes de importar}';

    protected $description = 'Importa games y woo_products desde los JSON generados por el script Python';

    public function handle(): int
    {
        $path = base_path($this->option('path'));

        foreach (['games.json', 'woo_products.json'] as $file) {
            if (! file_exists("$path/$file")) {
                $this->error("No encontré $path/$file");
                return self::FAILURE;
            }
        }

        if ($this->option('fresh')) {
            $this->warn('--fresh: vaciando tablas…');
            Schema::disableForeignKeyConstraints();
            DB::table('woo_products')->truncate();
            DB::table('games')->truncate();
            Schema::enableForeignKeyConstraints();
        }

        // 1) GAMES
        $games = json_decode(file_get_contents("$path/games.json"), true);
        $this->info("Importando " . count($games) . " juegos…");
        $bar = $this->output->createProgressBar(count($games));
        $slugToId = [];   // slug → game_id, lo usamos para los woo_products
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
        $this->newLine(2);

        $this->info("✓ Importación completa");
        $this->table(['Tabla', 'Filas'], [
            ['games',        count($slugToId)],
            ['woo_products', count($products)],
        ]);

        return self::SUCCESS;
    }
}
