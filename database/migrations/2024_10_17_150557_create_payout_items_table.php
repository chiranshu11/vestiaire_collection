<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayoutItemsTable extends Migration
{
    public function up()
    {
        Schema::create('item_payout', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('payout_id');
            
            $table->Integer('quantity')->default(1);
            // Foreign key constraints
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('payout_id')->references('id')->on('payouts')->onDelete('cascade');
        
            $table->timestamps();
            
            // Indexes for optimization
            $table->index('item_id');
            $table->index('payout_id');
        });
        
    }

    public function down()
    {
        Schema::dropIfExists('item_payout');
    }
}
