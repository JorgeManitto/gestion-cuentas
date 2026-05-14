<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // Pertenece a una order
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // Identidad del producto
            // wc_product_id: el "gameId" del payload del plugin (id de WooCommerce)
            $table->unsignedBigInteger('wc_product_id')->nullable()->index();
            $table->foreignId('game_id')
                ->nullable()
                ->constrained('games')
                ->nullOnDelete();
            $table->string('game_title');

            // Plataforma como vino del plugin (capitalizado: PlayStation/Xbox/Nintendo)
            // y plataforma normalizada para matching (PS5/PS4/XBOX_ONE/etc).
            $table->string('platform', 32)->index();              // raw del plugin
            $table->string('console_model_raw', 32)->nullable();  // "Xbox Series X/S"
            $table->string('platform_normalized', 24)->nullable()->index();  // "XBOX_SERIES"

            // Cuántas unidades pidió el cliente (típicamente 1, raro pero posible >1)
            $table->unsignedSmallInteger('quantity')->default(1);

            // Asignación
            $table->foreignId('account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->string('activation_key')->nullable();
            $table->string('who_delivered')->nullable();
            $table->timestamp('delivery_date')->nullable();

            // Status de nuestra entrega (DISTINTO al wc_status de la order header).
            //   pending     → recién creado, sin cuenta asignada
            //   in_progress → cuenta asignada, esperando entregar credenciales al cliente
            //   delivered   → credenciales enviadas al cliente
            //   cancelled
            //   failed      → sin cuenta disponible para asignar
            $table->enum('fulfillment_status', [
                'pending', 'in_progress', 'delivered', 'cancelled', 'failed',
            ])->default('pending')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
