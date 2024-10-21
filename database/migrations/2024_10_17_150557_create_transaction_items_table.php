<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionItemsTable extends Migration
{
    public function up()
    {
        Schema::create('item_transaction', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('transaction_id');
            
            $table->Integer('quantity')->default(1);
            // Foreign key constraints
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
        
            $table->timestamps();
            
            // Indexes for optimization
            $table->index('item_id');
            $table->index('transaction_id');
        });
        
    }

    public function down()
    {
        Schema::dropIfExists('item_payout');
    }
}
