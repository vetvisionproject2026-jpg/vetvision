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
    Schema::table('users', function (Blueprint $table) {
        // بنقوله: لو العمود مش موجود، ضيفه. لو موجود، عدي السطر ده.
        if (!Schema::hasColumn('users', 'verification_code')) {
            $table->string('verification_code')->nullable()->after('password');
        }

        if (!Schema::hasColumn('users', 'email_verified_at')) {
            $table->timestamp('email_verified_at')->nullable()->after('verification_code');
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
