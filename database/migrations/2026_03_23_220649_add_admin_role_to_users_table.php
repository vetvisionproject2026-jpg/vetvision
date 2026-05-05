<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // الطريقة الأضمن لتعديل الـ ENUM في MySQL
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'doctor', 'admin') DEFAULT 'user'");
    }

    public function down(): void
    {
        // للرجوع للحالة القديمة (حذف كلمة admin)
        // تنبيه: تأكد أنه لا يوجد مستخدم حالياً بدرو 'admin' قبل عمل rollback
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'doctor') DEFAULT 'user'");
    }
};