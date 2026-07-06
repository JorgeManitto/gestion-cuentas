<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // true si la orden tiene al menos un item que es membresía
            $table->boolean('has_membership')->default(false)->after('wc_status');
            // opcional: si después vas a filtrar órdenes con membresía
            // $table->index('has_membership');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('has_membership');
        });
    }
};