<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'price_amount',
        'price_currency',
        'seller_id'
    ];

    // Many-to-Many relationship with Payout using the pivot model ItemPayout
    public function payouts()
    {
        return $this->belongsToMany(Payout::class, 'item_payout', 'item_id', 'payout_id')
                    ->using(ItemPayout::class)  // Use the custom pivot model
                    ->withTimestamps(); // Include timestamps for pivot table
    }

    /**
     * Define a relationship between Item and Seller.
     * An item belongs to a seller.
     */
    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
}
