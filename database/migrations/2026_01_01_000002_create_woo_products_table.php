<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woo_products', function (Blueprint $table) {
            // El id ES el WooCommerce Product ID — sin id propio,
            // así sincronizar con la tienda no requiere mapeo extra.
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('game_id')
                ->nullable()
                ->constrained('games')
                ->nullOnDelete();
            $table->string('name');
            $table->string('platform', 32)->index();   // PS5, PS4, XboxOne, Switch, Steam...
            $table->string('image_url')->nullable();
            $table->text('category_raw')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woo_products');
    }
};
