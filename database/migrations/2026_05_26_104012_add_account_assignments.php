<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('account_assignments', function (Blueprint $table) {
            // PS4 / PS5 / XBOX_ONE / XBOX_SERIES / SWITCH / SWITCH_2 / STEAM
            $table->string('platform', 32)->nullable()->after('slot_number');
        });

        // Backfill: para las que ya existen, el platform sale del order_item
        // (platform_normalized); si no hay item, cae al platform de la cuenta.
        DB::statement("
            UPDATE account_assignments aa
            JOIN accounts a ON a.id = aa.account_id
            LEFT JOIN order_items oi ON oi.id = aa.order_item_id
            SET aa.platform = COALESCE(oi.platform_normalized, a.platform)
            WHERE aa.platform IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_assignments', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};
