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
        Schema::create('reviews', function (Blueprint $table) {
    $table->id();
    
    $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();

    $table->integer('rating'); // 1-5
    $table->text('comment')->nullable();

    $table->timestamp('reviewed_at')->nullable();

    $table->timestamps();

    // مهم جدًا 👇 يمنع تكرار التقييم لنفس الموعد
    $table->unique(['appointment_id']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
