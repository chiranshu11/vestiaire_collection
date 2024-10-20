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

    /**
     * Get the payout that owns the transaction.
     */
    public function payout()
    {
        return $this->belongsTo(Payout::class);
    }
}
