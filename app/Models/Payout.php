<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

     // Define the date format you want to use
     protected $dateFormat = 'Y-m-d H:i:s'; // Customize this format as needed

     // Define date attributes that should be converted to Carbon instances
     protected $dates = [
         'created_at',
         'updated_at',
     ];
 
     // Automatically convert the date to a string format when accessed
     protected $casts = [
         'created_at' => 'datetime',
         'updated_at' => 'datetime',
     ];
 
     // Define an accessor for created_at to format the date
     public function getCreatedAtAttribute($value)
     {
         return \Carbon::parse($value)->format($this->dateFormat);
     }
 
     // Define an accessor for updated_at to format the date
     public function getUpdatedAtAttribute($value)
     {
         return \Carbon::parse($value)->format($this->dateFormat);
     }

    //  this one was done for earlier payout service, will need this in demo
    //  Many-to-Many relationship with Item using the pivot model ItemPayout
    // public function items()
    // {
    //     return $this->belongsToMany(Item::class, 'item_payout', 'payout_id', 'item_id')
    //                 ->using(ItemPayout::class)  // Use the custom pivot model
    //                 ->withTimestamps(); // Include timestamps for pivot table
    // }

    /**
     * Define a relationship between Payout and Seller.
     * A payout belongs to a seller.
     */
    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function transactions() : HasMany
    {
        return $this->hasMany(Transaction::class);
    }

}
