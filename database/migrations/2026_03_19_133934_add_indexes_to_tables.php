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
        Schema::table('appointments', function (Blueprint $table) {
            // الـ doctor_id والـ user_id غالباً لديهم Index تلقائي بسبب الـ foreignId
            // لذا سنضيف فقط لـ date_time ونستخدم try-catch للسلامة
            try {
                $table->index('date_time'); 
            } catch (\Exception $e) {
                // إذا كان موجوداً بالفعل سيتجاهل الخطأ ويكمل
            }
        });

        Schema::table('animals', function (Blueprint $table) {
            // نتأكد أن العمود غير موجود قبل إضافته لتجنب فشل الميجريشن
            if (!Schema::hasColumn('animals', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // نحذف فقط الـ Index الخاص بـ date_time 
            // لأن حذف الآخرين قد يكسر الـ Foreign Keys
            $table->dropIndex(['date_time']);
        });

        Schema::table('animals', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    } 
};