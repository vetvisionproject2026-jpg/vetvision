<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // فحص هل الـ index موجود فعلاً قبل محاولة إضافته
            $conn = Schema::getConnection();
            $dbSchemaManager = $conn->getDoctrineSchemaManager();
            $indexes = $dbSchemaManager->listTableIndexes('appointments');

            if (!array_key_exists('appointments_date_time_index', $indexes)) {
                $table->index('date_time');
            }
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
            // حذف الـ index فقط لو كان موجوداً لتجنب الأخطاء عند التراجع
            $conn = Schema::getConnection();
            $dbSchemaManager = $conn->getDoctrineSchemaManager();
            $indexes = $dbSchemaManager->listTableIndexes('appointments');

            if (array_key_exists('appointments_date_time_index', $indexes)) {
                $table->dropIndex(['date_time']);
            }
        });

        Schema::table('animals', function (Blueprint $table) {
            if (Schema::hasColumn('animals', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    } 
};