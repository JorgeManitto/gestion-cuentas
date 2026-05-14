<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')
                ->constrained('accounts')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('slot_number');   // 1..4 (variable según plataforma)
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->date('assigned_at')->nullable();
            $table->enum('status', ['active', 'expired', 'revoked'])
                ->default('active')
                ->index();
            // Para cuando lleguen las orders del Woo
            $table->unsignedBigInteger('woo_order_id')->nullable()->index();
            $table->timestamps();

            // Un slot por cuenta no se puede asignar dos veces simultáneamente
            $table->unique(['account_id', 'slot_number']);
            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_assignments');
    }
};
