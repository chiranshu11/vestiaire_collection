<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ItemTransaction extends Pivot
{
    protected $table = 'item_transaction';

    protected $fillable = [
        'item_id',
        'transaction_id',
    ];
}
