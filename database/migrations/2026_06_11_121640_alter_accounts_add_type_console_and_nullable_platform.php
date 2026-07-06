<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('type_console', 50)->nullable()->after('platform');
            // $table->string('platform', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('type_console');
            // OJO: si quedaron filas con platform NULL, este change() va a fallar.
            // $table->string('platform', 50)->nullable(false)->change();
        });
    }
};