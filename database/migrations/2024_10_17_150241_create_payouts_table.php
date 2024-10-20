<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayoutsTable extends Migration
{
    public function up()
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->foreign('seller_id')->references('id')->on('sellers');

            $table->decimal('original_amount', 10, 2);
            $table->decimal('converted_amount', 10, 2);
            $table->string('original_currency', 3);
            $table->string('converted_currency', 3);
            
            $table->timestamps();

            // Adding indexes for optimization
            $table->index('seller_id'); // Index for quick lookup by seller reference
        });
    }

    public function down()
    {
        Schema::dropIfExists('payouts');
    }
}
