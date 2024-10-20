<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    // The attributes that are mass assignable
    protected $fillable = [
        'payout_id',
        'amount',
    ];

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

    // // Define an accessor for updated_at to format the date
    public function getUpdatedAtAttribute($value)
    {
        return \Carbon::parse($value)->format($this->dateFormat);
    }

    /**
     * Get the payout that owns the transaction.
     */
    public function payout()
    {
        return $this->belongsTo(Payout::class);
    }
}
