<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Motivo de deshabilitación: descanso, baneada, robada, otro.
            // Solo se completa cuando status != 'active'.
            $table->string('disable_reason', 32)->nullable()->after('status');
            $table->index('disable_reason');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['disable_reason']);
            $table->dropColumn('disable_reason');
        });
    }
};
