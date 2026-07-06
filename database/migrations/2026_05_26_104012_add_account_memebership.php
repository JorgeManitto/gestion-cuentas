<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('is_membership')->default(false)->after('account_type');
            // Duración en meses: 3, 6, 12. Null si no es membresía.
            $table->unsignedSmallInteger('membership_duration_months')->nullable()->after('is_membership');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['is_membership', 'membership_duration_months']);
        });
    }
};