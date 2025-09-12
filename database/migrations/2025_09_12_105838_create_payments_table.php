<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('biller_code')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('MYR');
            $table->string('email');
            $table->string('status')->default('initiated'); // initiated, open, success, failed, expired
            $table->string('zen_session_id')->nullable();
            $table->string('payref_id')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
