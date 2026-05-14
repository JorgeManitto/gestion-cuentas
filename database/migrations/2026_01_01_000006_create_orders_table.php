<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // ID de la order en WooCommerce. UNIQUE para idempotencia:
            // si el plugin reintenta, no duplica.
            $table->string('wc_order_id')->unique();

            // Cliente
            $table->string('customer_name');
            $table->string('customer_email')->index();

            // Plata
            $table->string('currency', 8);
            $table->string('total_raw')->nullable();   // "$25.3 (USD)" tal como vino del plugin
            $table->decimal('total_amount', 12, 2)->nullable();  // 25.30 parseado

            // Status de WooCommerce: pending, processing, on-hold,
            // completed, cancelled, refunded, failed. String en vez de enum
            // por si Woo agrega otros valores en el futuro.
            $table->string('wc_status', 32)->index();

            // Fechas
            $table->timestamp('order_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
