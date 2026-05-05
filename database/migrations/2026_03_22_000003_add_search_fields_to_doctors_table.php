<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (!Schema::hasColumn('doctors', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('clinic_address');
            }
            if (!Schema::hasColumn('doctors', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('doctors', 'consultation_fee')) {
                $table->integer('consultation_fee')->nullable()->after('longitude');
            }
            if (!Schema::hasColumn('doctors', 'average_rating')) {
                $table->float('average_rating')->default(0)->after('consultation_fee');
            }
            if (!Schema::hasColumn('doctors', 'image')) {
                $table->string('image')->nullable()->after('average_rating');
            }
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'consultation_fee', 'average_rating', 'image']);
        });
    }
};