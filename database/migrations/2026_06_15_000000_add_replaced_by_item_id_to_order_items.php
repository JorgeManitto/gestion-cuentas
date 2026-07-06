<?php
// database/migrations/xxxx_add_replaced_by_item_id_to_order_items.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('replaced_by_item_id')
                ->nullable()
                ->after('fulfillment_status')
                ->constrained('order_items')
                ->nullOnDelete(); // borrás el item-pack → los reemplazados quedan en null solos
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaced_by_item_id');
        });
    }
};