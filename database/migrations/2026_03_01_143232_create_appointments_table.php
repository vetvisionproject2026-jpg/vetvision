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
            // بنشيك لو الخانة مش موجودة بنضيفها
            if (!Schema::hasColumn('appointments', 'reminder_sent')) {
                $table->boolean('reminder_sent')->default(false)->after('consultation_fee');
            }
            
            if (!Schema::hasColumn('appointments', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('reminder_sent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // بنمسح الخانات دي بس لو عملنا Rollback
            $table->dropColumn(['reminder_sent', 'reminder_sent_at']);
        });
    }
};