<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemsTable extends Migration
{
    public function up()
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel_item_code');
            $table->decimal('price_amount', 10, 2);
            $table->string('price_currency', 3);
            $table->Integer('quantity');
            $table->unsignedBigInteger('seller_id');
            $table->foreign('seller_id')->references('id')->on('sellers');

            $table->timestamps();

            // Adding indexes for optimization
            $table->index('seller_id'); // Index for seller reference
            $table->index('seller_id','channel_item_code'); // Index for seller reference
        });
    }

    public function down()
    {
        Schema::dropIfExists('items');
    }
}
