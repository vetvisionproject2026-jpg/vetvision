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
    Schema::create('animals', function (Blueprint $table) {
        $table->id();
        $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
        $table->string('name');
        $table->string('species');
        $table->string('breed')->nullable();
        $table->integer('age')->nullable();
        $table->enum('gender', ['male', 'female', 'unknown']);
        $table->float('weight')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('animals');
    }
};
