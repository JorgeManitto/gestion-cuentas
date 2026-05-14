<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            // Vinculación opcional al item que la originó.
            // Nullable porque una OC también puede generarse "preventiva"
            // (sin item asociado, para reponer stock).
            $table->foreignId('order_item_id')
                ->nullable()
                ->constrained('order_items')
                ->nullOnDelete();

            // Qué se quiere comprar
            $table->foreignId('game_id')
                ->nullable()
                ->constrained('games')
                ->nullOnDelete();
            $table->string('game_title');
            $table->string('platform', 32)->index();
            $table->string('console_model', 64)->nullable();
            $table->string('region', 32)->default('sin especificar');
            $table->unsignedSmallInteger('quantity')->default(1);

            // Workflow de la OC
            //   pending   → identificamos la necesidad, falta comprar
            //   purchased → se hizo la compra al proveedor, esperando entrega
            //   received  → llegó la cuenta, está en inventario, OC cumplida
            //   cancelled → ya no hace falta
            $table->enum('status', ['pending', 'purchased', 'received', 'cancelled'])
                ->default('pending')
                ->index();

            $table->text('notes')->nullable();
            $table->date('arrival_date')->nullable();   // fecha estimada/real de llegada

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
