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
        Schema::table('account_assignments', function (Blueprint $table) {
            $table->string('key_value', 128)->nullable()->after('slot_number');
            $table->unsignedSmallInteger('key_position')->nullable()->after('key_value');
            $table->foreignId('order_item_id')->nullable()->after('woo_order_id')
                ->constrained('order_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['key_value', 'key_position', 'order_item_id']);
        });
    }
};
