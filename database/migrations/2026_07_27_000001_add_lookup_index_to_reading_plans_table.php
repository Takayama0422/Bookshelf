<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_plans', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'reading_plans_user_status_index');
            $table->index('target_date', 'reading_plans_target_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('reading_plans', function (Blueprint $table) {
            $table->dropIndex('reading_plans_user_status_index');
            $table->dropIndex('reading_plans_target_date_index');
        });
    }
};
