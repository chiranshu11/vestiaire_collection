<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ItemPayout extends Pivot
{
    protected $table = 'item_payout';

    protected $fillable = [
        'item_id',
        'payout_id',
    ];
}
