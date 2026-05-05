<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {

            // ✅ type — نوع الموعد
            if (!Schema::hasColumn('appointments', 'type')) {
                $table->enum('type', ['online', 'clinic', 'home_visit'])
                      ->default('clinic')
                      ->after('status');
            }

            // ✅ location fields — للـ home_visit
            if (!Schema::hasColumn('appointments', 'location')) {
                $table->string('location')->nullable()->after('type');
            }

            if (!Schema::hasColumn('appointments', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('location');
            }

            if (!Schema::hasColumn('appointments', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }

            // ✅ reminder fields
            if (!Schema::hasColumn('appointments', 'reminder_sent')) {
                $table->boolean('reminder_sent')->default(false)->after('consultation_fee');
            }

            if (!Schema::hasColumn('appointments', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('reminder_sent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'location',
                'latitude',
                'longitude',
                'reminder_sent',
                'reminder_sent_at',
            ]);
        });
    }
};