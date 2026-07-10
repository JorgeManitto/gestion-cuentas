<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Valor del atributo "qr" del producto en Woo. Si viene con contenido,
            // la entrega NO manda credenciales por mail (como preorden), pero SIN
            // el mensaje de preorden.
            $table->string('qr')->nullable()->after('is_pack');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('qr');
        });
    }
};
