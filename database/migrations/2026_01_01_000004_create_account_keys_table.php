<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')
                ->constrained('accounts')
                ->cascadeOnDelete();
            $table->string('key_value', 64);
            $table->unsignedTinyInteger('position');   // 1..N por cuenta
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            // Una posición no puede repetirse para la misma cuenta
            $table->unique(['account_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_keys');
    }
};
