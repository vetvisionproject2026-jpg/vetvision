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
    Schema::create('payments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('appointment_id')->constrained()->onDelete('cascade');
        $table->string('paymob_order_id')->nullable();
        $table->string('transaction_id')->nullable();
        $table->decimal('amount', 10, 2);
        $table->string('currency')->default('EGP');
        $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
