<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id');
            $table->string('unit_id',64)->nullable();
            $table->string('unit_name',64)->nullable();
            $table->string('customer_id',64)->nullable();
            $table->decimal('payment_amount', 24, 2)->default(0);
            $table->string('callback',191)->nullable();
            $table->string('hook',191)->nullable();
            $table->string('transaction_id',191)->nullable();
            $table->string('currency_code',10)->default('USD');
            $table->json('additional_data')->nullable();
            $table->string('payment_method',50)->nullable();
            $table->boolean('is_paid')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
