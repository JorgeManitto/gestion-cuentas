<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // price       => precio regular del producto
            // price_sale  => precio en oferta (null si el producto no estaba en oferta)
            $table->decimal('price', 12, 2)->nullable()->after('quantity');
            $table->decimal('price_sale', 12, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['price', 'price_sale']);
        });
    }
};
