<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // إضافة index مباشرة بدون فحص (Laravel يتعامل مع التكرار تلقائيًا في أغلب الحالات)
            $table->index('date_time');
        });

        Schema::table('animals', function (Blueprint $table) {
            if (!Schema::hasColumn('animals', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['date_time']);
        });

        Schema::table('animals', function (Blueprint $table) {
            if (Schema::hasColumn('animals', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};