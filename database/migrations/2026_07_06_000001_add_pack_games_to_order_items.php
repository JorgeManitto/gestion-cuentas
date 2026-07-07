<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Juegos que el cliente eligió dentro de un pack (isPack), tal como los
            // manda Woo: [{ game_id, game_title, platform }, ...]. Null en ítems no-pack
            // o en packs viejos anteriores a esta funcionalidad.
            $table->json('pack_games')->nullable()->after('is_pack');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('pack_games');
        });
    }
};
