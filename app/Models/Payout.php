<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'seller_id',
        'original_amount',
        'converted_amount',
        'original_currency',
        'converted_currency'
    ];

    // Many-to-Many relationship with Item using the pivot model ItemPayout
    public function items()
    {
        return $this->belongsToMany(Item::class, 'item_payout', 'payout_id', 'item_id')
                    ->using(ItemPayout::class)  // Use the custom pivot model
                    ->withTimestamps(); // Include timestamps for pivot table
    }

    /**
     * Define a relationship between Payout and Seller.
     * A payout belongs to a seller.
     */
    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
}
