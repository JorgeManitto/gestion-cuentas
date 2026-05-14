<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            // Juego al que pertenece la cuenta
            $table->foreignId('game_id')
                ->nullable()
                ->constrained('games')
                ->nullOnDelete();

            // Auto-FK para la jerarquía MADRE → HIJA
            $table->foreignId('parent_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();

            // string en vez de enum: cuando agregues XBOX_ONE / SWITCH / STEAM
            // no hace falta migration nueva, solo importás cuentas y listo.
            $table->string('platform', 24)->index();
            $table->enum('account_type', ['INDEPENDIENTE', 'MADRE', 'HIJA'])->index();
            $table->string('region', 32)->index();   // USA, BRASIL, TURKIA, INDIA, etc.

            // Cuenta del servicio (PSN, Xbox, Nintendo, Steam, etc)
            $table->string('email');
            $table->string('password');

            // Correo electrónico asociado a la cuenta (puede ser distinto al `email` de arriba)
            $table->string('mail_email')->nullable();
            $table->string('mail_password')->nullable();

            // Fechas
            $table->date('created_date')->nullable();
            $table->date('purchased_date')->nullable();
            $table->date('reset_date')->nullable();

            // Identificación pública en la plataforma
            // (PSN Online ID / Xbox Gamertag / Nintendo Account / Steam username)
            $table->string('gamer_tag')->nullable();
            $table->date('birth_date')->nullable();

            // Estado y notas
            $table->enum('status', ['active', 'blocked', 'reset', 'archived'])
                ->default('active')
                ->index();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
