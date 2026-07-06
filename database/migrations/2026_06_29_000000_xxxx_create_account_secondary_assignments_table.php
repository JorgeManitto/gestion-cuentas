<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_secondary_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('slot_number');
            $table->string('platform')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->date('assigned_at')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('woo_order_id')->nullable();
            $table->string('key_value')->nullable();
            $table->unsignedInteger('key_position')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_secondary_assignments');
    }
};