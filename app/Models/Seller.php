<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'id', 'name', 'email', 'phone', 'address', 'city', 'pincode',
        'billing_name', 'billing_email', 'billing_phone', 'billing_address',
        'base_currency', 'status', 'cin'
    ];

    /**
     * Define a relationship between Seller and Payout.
     * A seller can have many payouts.
     */
    public function payouts()
    {
        return $this->hasMany(Payout::class);
    }

    /**
     * Define a relationship between Seller and Item.
     * A seller can have many items.
     */
    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
