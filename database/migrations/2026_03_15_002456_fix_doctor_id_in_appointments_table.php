<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Remove old foreign key to users table
            $table->dropForeign(['doctor_id']); 

            // Link it to the new doctors table
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['doctor_id']);
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
}; // Removed the extra closing braces and fixed the semi-colon placement