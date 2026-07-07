<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prórroga / snooze de rotación.
     *
     * Fecha hasta la cual la cuenta NO debe aparecer en los recomendados a
     * resetear, aunque ya cumpla la ventana real (6 meses desde compra/reset).
     *
     * Se usa cuando no se sabe con certeza la fecha real de reset (se reseteó
     * desde Sony y no se registró acá): el operador pospone N meses y la cuenta
     * se oculta durante ese plazo. Cumplido el plazo, vuelven a aplicar las
     * reglas normales. NO pisa purchased_date ni reset_date.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->date('reset_snooze_until')->nullable()->after('reset_date');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('reset_snooze_until');
        });
    }
};
