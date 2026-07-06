<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill: filas legacy sin platform usan la de su cuenta
        DB::statement("
            UPDATE account_assignments aa
            JOIN accounts a ON a.id = aa.account_id
            SET aa.platform = a.platform
            WHERE aa.platform IS NULL
        ");

        Schema::table('account_assignments', function (Blueprint $table) {
            // 1) Primero el nuevo (empieza por account_id → cubre la FK)
            $table->unique(['account_id', 'platform', 'slot_number']);
            // 2) Recién ahora se puede soltar el viejo
            $table->dropUnique('account_assignments_account_id_slot_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('account_assignments', function (Blueprint $table) {
            $table->unique(['account_id', 'slot_number']);
            $table->dropUnique(['account_id', 'platform', 'slot_number']);
        });
    }
    
};
