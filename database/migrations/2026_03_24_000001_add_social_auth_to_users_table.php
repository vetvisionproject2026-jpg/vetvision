<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // اسم الـ Provider: هيكون 'google' أو 'facebook'
            $table->string('provider')->nullable()->after('email');

            // الـ ID الخاص بالمستخدم عند Google/Facebook
            $table->string('provider_id')->nullable()->after('provider');

            // الـ password بيبقى nullable لأن Social Users مش عندهم password
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['provider', 'provider_id']);
            $table->string('password')->nullable(false)->change();
        });
    }
};