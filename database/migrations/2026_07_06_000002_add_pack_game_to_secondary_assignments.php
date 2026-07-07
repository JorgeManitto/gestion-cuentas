<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_secondary_assignments', function (Blueprint $table) {
            // Qué juego solicitado del pack cubre esta cuenta (trazabilidad en la vista
            // de entregado). Null en asignaciones secundarias que no vienen de un pack
            // con juegos preseleccionados.
            $table->unsignedBigInteger('pack_game_id')->nullable()->after('order_item_id');
            $table->string('pack_game_title')->nullable()->after('pack_game_id');
        });
    }

    public function down(): void
    {
        Schema::table('account_secondary_assignments', function (Blueprint $table) {
            $table->dropColumn(['pack_game_id', 'pack_game_title']);
        });
    }
};
