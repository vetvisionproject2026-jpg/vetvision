<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            // بنشيك الأول لو العمود مش موجود عشان ميديناش Error
            if (!Schema::hasColumn('doctors', 'license_number')) {
                $table->string('license_number')->nullable();
            }
            if (!Schema::hasColumn('doctors', 'license_expiry')) {
                $table->date('license_expiry')->nullable();
            }
            if (!Schema::hasColumn('doctors', 'license_image')) {
                $table->string('license_image')->nullable();
            }
            if (!Schema::hasColumn('doctors', 'selfie_image')) {
                $table->string('selfie_image')->nullable();
            }
            if (!Schema::hasColumn('doctors', 'verification_status')) {
                $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            }
            if (!Schema::hasColumn('doctors', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn([
                'license_number', 
                'license_expiry', 
                'license_image', 
                'selfie_image', 
                'verification_status', 
                'rejection_reason'
            ]);
        });
    }
};