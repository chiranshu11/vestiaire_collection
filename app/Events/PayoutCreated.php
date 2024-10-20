<?php
namespace App\Events;

use App\Models\Payout;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class PayoutCreated
{
    use Dispatchable, SerializesModels;

    //Event created so that payout is captured 
    public $payout;

    // Constructor to initialize Payout for the event
    public function __construct(Payout $payout)
    {
        $this->payout = $payout;
    }
}
