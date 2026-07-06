<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_assignments', function (Blueprint $table) {
            // 1) Índice plano sobre account_id → la FK pasa a apoyarse acá
            $table->index('account_id', 'account_assignments_account_id_index');
        });

        Schema::table('account_assignments', function (Blueprint $table) {
            // 2) Ahora sí se puede soltar el único (la FK ya no lo necesita)
            $table->dropUnique('account_assignments_account_id_platform_slot_number_unique');
        });

        // 3) Índice único active-only (emulado con columna generada)
        DB::statement("
            ALTER TABLE account_assignments
            ADD COLUMN active_slot_key VARCHAR(64)
            GENERATED ALWAYS AS (
                CASE WHEN status = 'active'
                    THEN CONCAT(account_id, '-', platform, '-', slot_number)
                    ELSE NULL
                END
            ) VIRTUAL
        ");

        DB::statement("
            CREATE UNIQUE INDEX account_assignments_active_slot_unique
            ON account_assignments (active_slot_key)
        ");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX account_assignments_active_slot_unique ON account_assignments");
        DB::statement("ALTER TABLE account_assignments DROP COLUMN active_slot_key");

        Schema::table('account_assignments', function (Blueprint $table) {
            $table->unique(['account_id', 'platform', 'slot_number']);
        });

        Schema::table('account_assignments', function (Blueprint $table) {
            $table->dropIndex('account_assignments_account_id_index');
        });
    }
    
};
