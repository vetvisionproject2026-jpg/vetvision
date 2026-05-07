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
        Schema::create('appointments', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('animal_id');

            $table->dateTime('date_time');

            $table->enum('status', [
                'pending',
                'confirmed',
                'cancelled',
                'completed'
            ])->default('pending');

            $table->string('reason');

            $table->string('location')->nullable();

            $table->integer('duration')->default(30);

            $table->text('notes')->nullable();

            $table->integer('rating')->nullable();

            $table->text('review')->nullable();

            $table->enum('type', [
                'online',
                'clinic',
                'home_visit'
            ])->default('clinic');

            $table->decimal('latitude', 10, 8)->nullable();

            $table->decimal('longitude', 11, 8)->nullable();

            $table->integer('consultation_fee')->nullable();

            $table->boolean('reminder_sent')->default(false);

            $table->timestamp('reminder_sent_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};