<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            // إضافة عمود الحالة بعد عمود التعليمات
            $table->enum('status', ['active', 'completed', 'discontinued'])
                  ->default('active')
                  ->after('instructions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            // حذف العمود في حال عمل rollback
            $table->dropColumn('status');
        });
    }
};