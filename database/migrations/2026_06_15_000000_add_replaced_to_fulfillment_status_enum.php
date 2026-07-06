<?php
// database/migrations/xxxx_add_replaced_to_fulfillment_status_enum.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE order_items
            MODIFY COLUMN fulfillment_status
            ENUM('pending', 'in_progress', 'delivered', 'cancelled', 'failed', 'replaced')
            NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        // OJO: si hay filas con 'replaced', este rollback va a fallar.
        // Las reseteamos a 'pending' antes de achicar el enum.
        DB::table('order_items')
            ->where('fulfillment_status', 'replaced')
            ->update(['fulfillment_status' => 'pending']);

        DB::statement("
            ALTER TABLE order_items
            MODIFY COLUMN fulfillment_status
            ENUM('pending', 'in_progress', 'delivered', 'cancelled', 'failed')
            NOT NULL DEFAULT 'pending'
        ");
    }
};